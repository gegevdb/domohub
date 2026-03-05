"use strict";
var __createBinding = (this && this.__createBinding) || (Object.create ? (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    var desc = Object.getOwnPropertyDescriptor(m, k);
    if (!desc || ("get" in desc ? !m.__esModule : desc.writable || desc.configurable)) {
      desc = { enumerable: true, get: function() { return m[k]; } };
    }
    Object.defineProperty(o, k2, desc);
}) : (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    o[k2] = m[k];
}));
var __setModuleDefault = (this && this.__setModuleDefault) || (Object.create ? (function(o, v) {
    Object.defineProperty(o, "default", { enumerable: true, value: v });
}) : function(o, v) {
    o["default"] = v;
});
var __decorate = (this && this.__decorate) || function (decorators, target, key, desc) {
    var c = arguments.length, r = c < 3 ? target : desc === null ? desc = Object.getOwnPropertyDescriptor(target, key) : desc, d;
    if (typeof Reflect === "object" && typeof Reflect.decorate === "function") r = Reflect.decorate(decorators, target, key, desc);
    else for (var i = decorators.length - 1; i >= 0; i--) if (d = decorators[i]) r = (c < 3 ? d(r) : c > 3 ? d(target, key, r) : d(target, key)) || r;
    return c > 3 && r && Object.defineProperty(target, key, r), r;
};
var __importStar = (this && this.__importStar) || (function () {
    var ownKeys = function(o) {
        ownKeys = Object.getOwnPropertyNames || function (o) {
            var ar = [];
            for (var k in o) if (Object.prototype.hasOwnProperty.call(o, k)) ar[ar.length] = k;
            return ar;
        };
        return ownKeys(o);
    };
    return function (mod) {
        if (mod && mod.__esModule) return mod;
        var result = {};
        if (mod != null) for (var k = ownKeys(mod), i = 0; i < k.length; i++) if (k[i] !== "default") __createBinding(result, mod, k[i]);
        __setModuleDefault(result, mod);
        return result;
    };
})();
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const bind_decorator_1 = __importDefault(require("bind-decorator"));
const json_stable_stringify_without_jsonify_1 = __importDefault(require("json-stable-stringify-without-jsonify"));
const device_1 = __importDefault(require("../model/device"));
const logger_1 = __importDefault(require("../util/logger"));
const settings = __importStar(require("../util/settings"));
const utils_1 = __importDefault(require("../util/utils"));
const extension_1 = __importDefault(require("./extension"));
/**
 * This extension calls the zigbee-herdsman-converters definition configure() method
 */
class Configure extends extension_1.default {
    configuring = new Set();
    attempts = new Map();
    topic = `${settings.get().mqtt.base_topic}/bridge/request/device/configure`;
    async onReconfigure(data) {
        // Disabling reporting unbinds some cluster which could be bound by configure, re-setup.
        if (data.device.zh.meta?.configured !== undefined) {
            delete data.device.zh.meta.configured;
            data.device.zh.save();
        }
        await this.configure(data.device, "reporting_disabled");
    }
    async onMQTTMessage(data) {
        if (data.topic === this.topic) {
            const message = utils_1.default.parseJSON(data.message, data.message);
            const ID = typeof message === "object" ? message.id : message;
            let error;
            if (ID === undefined) {
                error = "Invalid payload";
            }
            else {
                const device = this.zigbee.resolveEntity(ID);
                if (!device || !(device instanceof device_1.default)) {
                    error = `Device '${ID}' does not exist`;
                }
                else if (!device.definition || !device.definition.configure) {
                    error = `Device '${device.name}' cannot be configured`;
                }
                else {
                    try {
                        await this.configure(device, "mqtt_message", true, true);
                    }
                    catch (e) {
                        error = `Failed to configure (${e.message})`;
                    }
                }
            }
            const response = utils_1.default.getResponse(message, { id: ID }, error);
            await this.mqtt.publish("bridge/response/device/configure", (0, json_stable_stringify_without_jsonify_1.default)(response));
        }
    }
    start() {
        setImmediate(async () => {
            // Only configure routers on startup, end devices are likely sleeping and
            // will reconfigure once they send a message
            for (const device of this.zigbee.devicesIterator((d) => d.type === "Router")) {
                // Sleep 10 seconds between configuring on startup to not DDoS the coordinator when many devices have to be configured.
                await utils_1.default.sleep(10);
                await this.configure(device, "started");
            }
        });
        this.eventBus.onDeviceJoined(this, async (data) => {
            if (data.device.zh.meta.configured !== undefined) {
                delete data.device.zh.meta.configured;
                data.device.zh.save();
            }
            await this.configure(data.device, "zigbee_event");
        });
        // TODO: this is triggering for any `data.status`, but should only for `successful`? (relies on `device.definition?` early-return?)
        this.eventBus.onDeviceInterview(this, (data) => this.configure(data.device, "zigbee_event"));
        this.eventBus.onLastSeenChanged(this, (data) => this.configure(data.device, "zigbee_event"));
        this.eventBus.onMQTTMessage(this, this.onMQTTMessage);
        this.eventBus.onReconfigure(this, this.onReconfigure);
        return Promise.resolve();
    }
    async configure(device, event, force = false, throwError = false) {
        if (!device.definition?.configure) {
            return;
        }
        const definitionVersion = device.definition.version;
        if (!force) {
            if (device.options.disabled || !device.interviewed) {
                return;
            }
            // Only configure end devices when it is active, otherwise it will likely fails as they are sleeping.
            if (device.zh.type === "EndDevice" && event !== "zigbee_event") {
                return;
            }
            const shouldReconfigure = 
            // Should always reconfigure when not configured before
            device.zh.meta?.configured === undefined ||
                // Or should reconfigure when definition.version is not '0.0.0' and differs from last `meta.configured`.
                // In older Z2M versions the stored `meta.configured` was the hash of the configure function.
                // Since we don't want to reconfigure all devices, we don't re-configure when the definition has the default version of '0.0.0'.
                (definitionVersion !== "0.0.0" && device.zh.meta?.configured !== definitionVersion);
            if (!shouldReconfigure) {
                return;
            }
        }
        if (this.configuring.has(device.ieeeAddr)) {
            return;
        }
        const attempts = this.attempts.get(device.ieeeAddr) ?? 0;
        if (attempts >= 3 && !force) {
            return;
        }
        this.configuring.add(device.ieeeAddr);
        logger_1.default.info(`Configuring '${device.name}'`);
        try {
            await device.definition.configure(device.zh, this.zigbee.firstCoordinatorEndpoint(), device.definition);
            this.attempts.delete(device.ieeeAddr);
            logger_1.default.info(`Successfully configured '${device.name}' (definition v${definitionVersion})`);
            device.zh.meta.configured = definitionVersion;
            device.zh.save();
            this.eventBus.emitDevicesChanged();
        }
        catch (error) {
            const newAttempts = attempts + 1;
            this.attempts.set(device.ieeeAddr, newAttempts);
            const msg = `Failed to configure '${device.name}', attempt ${newAttempts} (${error.stack})`;
            logger_1.default.error(msg);
            if (throwError) {
                throw error;
            }
        }
        finally {
            this.configuring.delete(device.ieeeAddr);
        }
    }
}
exports.default = Configure;
__decorate([
    bind_decorator_1.default
], Configure.prototype, "onReconfigure", null);
__decorate([
    bind_decorator_1.default
], Configure.prototype, "onMQTTMessage", null);
//# sourceMappingURL=data:application/json;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoiY29uZmlndXJlLmpzIiwic291cmNlUm9vdCI6IiIsInNvdXJjZXMiOlsiLi4vLi4vbGliL2V4dGVuc2lvbi9jb25maWd1cmUudHMiXSwibmFtZXMiOltdLCJtYXBwaW5ncyI6Ijs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7QUFBQSxvRUFBa0M7QUFDbEMsa0hBQThEO0FBQzlELDZEQUFxQztBQUVyQyw0REFBb0M7QUFDcEMsMkRBQTZDO0FBQzdDLDBEQUFrQztBQUNsQyw0REFBb0M7QUFFcEM7O0dBRUc7QUFDSCxNQUFxQixTQUFVLFNBQVEsbUJBQVM7SUFDcEMsV0FBVyxHQUFHLElBQUksR0FBRyxFQUFVLENBQUM7SUFDaEMsUUFBUSxHQUFHLElBQUksR0FBRyxFQUFrQixDQUFDO0lBQ3JDLEtBQUssR0FBRyxHQUFHLFFBQVEsQ0FBQyxHQUFHLEVBQUUsQ0FBQyxJQUFJLENBQUMsVUFBVSxrQ0FBa0MsQ0FBQztJQUVoRSxBQUFOLEtBQUssQ0FBQyxhQUFhLENBQUMsSUFBMkI7UUFDekQsd0ZBQXdGO1FBQ3hGLElBQUksSUFBSSxDQUFDLE1BQU0sQ0FBQyxFQUFFLENBQUMsSUFBSSxFQUFFLFVBQVUsS0FBSyxTQUFTLEVBQUUsQ0FBQztZQUNoRCxPQUFPLElBQUksQ0FBQyxNQUFNLENBQUMsRUFBRSxDQUFDLElBQUksQ0FBQyxVQUFVLENBQUM7WUFDdEMsSUFBSSxDQUFDLE1BQU0sQ0FBQyxFQUFFLENBQUMsSUFBSSxFQUFFLENBQUM7UUFDMUIsQ0FBQztRQUVELE1BQU0sSUFBSSxDQUFDLFNBQVMsQ0FBQyxJQUFJLENBQUMsTUFBTSxFQUFFLG9CQUFvQixDQUFDLENBQUM7SUFDNUQsQ0FBQztJQUVtQixBQUFOLEtBQUssQ0FBQyxhQUFhLENBQUMsSUFBMkI7UUFDekQsSUFBSSxJQUFJLENBQUMsS0FBSyxLQUFLLElBQUksQ0FBQyxLQUFLLEVBQUUsQ0FBQztZQUM1QixNQUFNLE9BQU8sR0FBRyxlQUFLLENBQUMsU0FBUyxDQUFD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