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
exports.HomeAssistant = void 0;
const node_assert_1 = __importDefault(require("node:assert"));
const bind_decorator_1 = __importDefault(require("bind-decorator"));
const json_stable_stringify_without_jsonify_1 = __importDefault(require("json-stable-stringify-without-jsonify"));
const logger_1 = __importDefault(require("../util/logger"));
const settings = __importStar(require("../util/settings"));
const utils_1 = __importStar(require("../util/utils"));
const extension_1 = __importDefault(require("./extension"));
const ACTION_PATTERNS = [
    "^(?<button>(?:button_)?[a-z0-9]+)_(?<action>(?:press|hold)(?:_release)?)$",
    "^(?<action>recall|scene)_(?<scene>[0-2][0-9]{0,2})$",
    "^(?<actionPrefix>region_)(?<region>[1-9]|10)_(?<action>enter|leave|occupied|unoccupied)$",
    "^(?<action>dial_rotate)_(?<direction>left|right)_(?<speed>step|slow|fast)$",
    "^(?<action>brightness_step)(?:_(?<direction>up|down))?$",
];
const ACCESS_STATE = 0b001;
const ACCESS_SET = 0b010;
const GROUP_SUPPORTED_TYPES = ["light", "switch", "lock", "cover"];
const COVER_OPENING_LOOKUP = ["opening", "open", "forward", "up", "rising"];
const COVER_CLOSING_LOOKUP = ["closing", "close", "backward", "back", "reverse", "down", "declining"];
const COVER_STOPPED_LOOKUP = ["stopped", "stop", "pause", "paused"];
const SWITCH_DIFFERENT = ["valve_detection", "window_detection", "auto_lock", "away_mode"];
const BINARY_DISCOVERY_LOOKUP = {
    activity_led_indicator: { icon: "mdi:led-on" },
    area1Occupancy: { device_class: "occupancy" },
    area2Occupancy: { device_class: "occupancy" },
    area3Occupancy: { device_class: "occupancy" },
    area4Occupancy: { device_class: "occupancy" },
    auto_off: { icon: "mdi:flash-auto" },
    battery_low: { entity_category: "diagnostic", device_class: "battery" },
    button_lock: { entity_category: "config", icon: "mdi:lock" },
    calibration: { entity_category: "config", icon: "mdi:progress-wrench" },
    capabilities_configurable_curve: { entity_category: "diagnostic", icon: "mdi:tune" },
    capabilities_forward_phase_control: { entity_category: "diagnostic", icon: "mdi:tune" },
    capabilities_overload_detection: { entity_category: "diagnostic", icon: "mdi:tune" },
    capabilities_reactance_discriminator: { entity_category: "diagnostic", icon: "mdi:tune" },
    capabilities_reverse_phase_control: { entity_category: "diagnostic", icon: "mdi:tune" },
    carbon_monoxide: { device_class: "carbon_monoxide" },
    card: { entity_category: "config", icon: "mdi:clipboard-check" },
    child_lock: { entity_category: "config", icon: "mdi:account-lock" },
    color_sync: { entity_category: "config", icon: "mdi:sync-circle" },
    consumer_connected: { device_class: "plug" },
    contact: { device_class: "door" },
    garage_door_contact: { device_class: "garage_door", payload_on: false, payload_off: true },
    eco_mode: { entity_category: "config", icon: "mdi:leaf" },
    expose_pin: { entity_category: "config", icon: "mdi:pin" },
    flip_indicator_light: { entity_category: "config", icon: "mdi:arrow-left-right" },
    gas: { device_class: "gas" },
    indicator_mode: { entity_category: "config", icon: "mdi:led-on" },
    invert_cover: { entity_category: "config", icon: "mdi:arrow-left-right" },
    led_disabled_night: { entity_category: "config", icon: "mdi:led-off" },
    led_indication: { entity_category: "config", icon: "mdi:led-on" },
    led_enable: { entity_category: "config", icon: "mdi:led-on" },
    motor_reversal: { entity_category: "config", icon: "mdi:arrow-left-right" },
    moving: { device_class: "moving" },
    no_position_support: { entity_category: "config", icon: "mdi:minus-circle-outline" },
    noise_detected: { device_class: "sound" },
    occupancy: { device_class: "occupancy" },
    power_outage_memory: { entity_category: "config", icon: "mdi:memory" },
    presence: { device_class: "occupancy" },
    rain_status: { device_class: "moisture", icon: "mdi:weather-pouring" },
    setup: { device_class: "running" },
    smoke: { device_class: "smoke" },
    sos: { device_class: "safety" },
    schedule: { icon: "mdi:calendar" },
    status_capacitive_load: { entity_category: "diagnostic", icon: "mdi:tune" },
    status_forward_phase_control: { entity_category: "diagnostic", icon: "mdi:tune" },
    status_inductive_load: { entity_category: "diagnostic", icon: "mdi:tune" },
    status_overload: { entity_category: "diagnostic", icon: "mdi:tune" },
    status_reverse_phase_control: { entity_category: "diagnostic", icon: "mdi:tune" },
    tamper: { device_class: "tamper" },
    temperature_scale: { entity_category: "config", icon: "mdi:temperature-celsius" },
    test: { entity_category: "diagnostic", icon: "mdi:test-tube" },
    th_heater: { icon: "mdi:heat-wave" },
    trigger_indicator: { icon: "mdi:led-on" },
    valve_alarm: { device_class: "problem" },
    valve_detection: { icon: "mdi:pipe-valve" },
    valve_state: { device_class: "opening" },
    vibration: { device_class: "vibration" },
    water_leak: { device_class: "moisture" },
    window: { device_class: "window" },
    window_detection: { icon: "mdi:window-open-variant" },
    window_open: { device_class: "window" },
};
const NUMERIC_DISCOVERY_LOOKUP = {
    ac_frequency: { device_class: "frequency", state_class: "measurement" },
    action_duration: { icon: "mdi:timer", device_class: "duration" },
    alarm_humidity_max: { device_class: "humidity", entity_category: "config", icon: "mdi:water-plus" },
    alarm_humidity_min: { device_class: "humidity", entity_category: "config", icon: "mdi:water-minus" },
    alarm_temperature_max: { device_class: "temperature", entity_category: "config", icon: "mdi:thermometer-high" },
    alarm_temperature_min: { device_class: "temperature", entity_category: "config", icon: "mdi:thermometer-low" },
    angle: { icon: "angle-acute" },
    angle_axis: { icon: "angle-acute" },
    apparent_temperature: { device_class: "temperature", icon: "mdi:thermometer-lines", state_class: "measurement" },
    aqi: { device_class: "aqi", state_class: "measurement" },
    auto_relock_time: { entity_category: "config", icon: "mdi:timer" },
    away_preset_days: { entity_category: "config", icon: "mdi:timer" },
    away_preset_temperature: { entity_category: "config", icon: "mdi:thermometer" },
    ballast_maximum_level: { entity_category: "config" },
    ballast_minimum_level: { entity_category: "config" },
    ballast_physical_maximum_level: { entity_category: "diagnostic" },
    ballast_physical_minimum_level: { entity_category: "diagnostic" },
    battery: { device_class: "battery", state_class: "measurement" },
    battery2: { device_class: "battery", entity_category: "diagnostic", state_class: "measurement" },
    battery_voltage: { device_class: "voltage", entity_category: "diagnostic", state_class: "measurement", enabled_by_default: true },
    boost_heating_countdown: { device_class: "duration" },
    boost_heating_countdown_time_set: { entity_category: "config", icon: "mdi:timer" },
    boost_time: { entity_category: "config", icon: "mdi:timer" },
    calibration: { entity_category: "config", icon: "mdi:wrench-clock" },
    calibration_time: { entity_category: "config", icon: "mdi:wrench-clock" },
    co2: { device_class: "carbon_dioxide", state_class: "measurement" },
    comfort_temperature: { entity_category: "config", icon: "mdi:thermometer" },
    cpu_temperature: {
        device_class: "temperature",
        entity_category: "diagnostic",
        state_class: "measurement",
    },
    cube_side: { icon: "mdi:cube" },
    current: { device_class: "current", state_class: "measurement" },
    current_phase_b: { device_class: "current", state_class: "measurement" },
    current_phase_c: { device_class: "current", state_class: "measurement" },
    deadzone_temperature: { entity_category: "config", icon: "mdi:thermometer" },
    detection_interval: { icon: "mdi:timer" },
    device_temperature: {
        device_class: "temperature",
        entity_category: "diagnostic",
        state_class: "measurement",
    },
    dew_point: { device_class: "temperature", icon: "mdi:thermometer-water", state_class: "measurement" },
    distance: { device_class: "distance", state_class: "measurement" },
    duration: { entity_category: "config", icon: "mdi:timer" },
    eco2: { device_class: "volatile_organic_compounds_parts", state_class: "measurement" },
    eco_temperature: { entity_category: "config", icon: "mdi:thermometer" },
    energy: { device_class: "energy", state_class: "total_increasing" },
    external_temperature_input: { device_class: "temperature", icon: "mdi:thermometer" },
    external_temperature: { device_class: "temperature", icon: "mdi:thermometer", state_class: "measurement" },
    external_humidity: { device_class: "humidity", icon: "mdi:water-percent", state_class: "measurement" },
    formaldehyd: { state_class: "measurement" },
    flow: { device_class: "volume_flow_rate", state_class: "measurement" },
    gas: { device_class: "gas", state_class: "total_increasing", icon: "mdi:meter-gas" },
    gas_density: { icon: "mdi:google-circles-communities", state_class: "measurement" },
    gust_speed: { device_class: "wind_speed", icon: "mdi:weather-windy-variant", state_class: "measurement" },
    hcho: { icon: "mdi:air-filter", state_class: "measurement" },
    heat_stress: { icon: "mdi:weather-sunny-alert", state_class: "measurement" },
    humidex: { device_class: "temperature", icon: "mdi:thermometer-alert", state_class: "measurement" },
    humidity: { device_class: "humidity", state_class: "measurement" },
    humidity_calibration: { entity_category: "config", icon: "mdi:wrench-clock" },
    humidity_max: { entity_category: "config", icon: "mdi:water-percent" },
    humidity_min: { entity_category: "config", icon: "mdi:water-percent" },
    illuminance_calibration: { entity_category: "config", icon: "mdi:wrench-clock" },
    illuminance: { device_class: "illuminance", state_class: "measurement" },
    illuminance_raw: { state_class: "measurement" },
    internalTemperature: {
        device_class: "temperature",
        entity_category: "diagnostic",
        state_class: "measurement",
    },
    linkquality: {
        enabled_by_default: false,
        entity_category: "diagnostic",
        icon: "mdi:signal",
        state_class: "measurement",
    },
    load_estimate: { state_class: "measurement" },
    local_temperature: { device_class: "temperature", state_class: "measurement" },
    max_range: { entity_category: "config", icon: "mdi:signal-distance-variant" },
    max_temperature: { entity_category: "config", icon: "mdi:thermometer-high" },
    max_temperature_limit: { entity_category: "config", icon: "mdi:thermometer-high" },
    min_temperature_limit: { entity_category: "config", icon: "mdi:thermometer-low" },
    min_temperature: { entity_category: "config", icon: "mdi:thermometer-low" },
    minimum_on_level: { entity_category: "config" },
    measurement_poll_interval: { entity_category: "config", icon: "mdi:clock-out" },
    motion_sensitivity: { entity_category: "config", icon: "mdi:motion-sensor" },
    noise: { device_class: "sound_pressure", state_class: "measurement" },
    noise_detect_level: { icon: "mdi:volume-equal" },
    noise_timeout: { icon: "mdi:timer" },
    occupancy_level: { icon: "mdi:motion-sensor", state_class: "measurement" },
    occupancy_sensitivity: { entity_category: "config", icon: "mdi:motion-sensor" },
    occupancy_timeout: { entity_category: "config", icon: "mdi:timer" },
    overload_protection: { icon: "mdi:flash" },
    pm10: { device_class: "pm10", state_class: "measurement" },
    pm25: { device_class: "pm25", state_class: "measurement" },
    people: { state_class: "measurement", icon: "mdi:account-multiple" },
    position: { icon: "mdi:valve", state_class: "measurement" },
    power: { device_class: "power", state_class: "measurement" },
    power_phase_b: { device_class: "power", state_class: "measurement" },
    power_phase_c: { device_class: "power", state_class: "measurement" },
    power_factor: { device_class: "power_factor", enabled_by_default: false, entity_category: "diagnostic", state_class: "measurement" },
    power_outage_count: { icon: "mdi:counter", enabled_by_default: false, state_class: "measurement" },
    precipitation: { device_class: "precipitation", icon: "mdi:weather-rainy", state_class: "total_increasing" },
    precision: { entity_category: "config", icon: "mdi:decimal-comma-increase" },
    pressure: { device_class: "atmospheric_pressure", state_class: "measurement" },
    pressure_trend: { icon: "mdi:trending-up", state_class: "measurement" },
    presence_timeout: { entity_category: "config", icon: "mdi:timer" },
    rain_rate: { device_class: "precipitation_intensity", icon: "mdi:weather-pouring", state_class: "measurement" },
    reporting_time: { entity_category: "config", icon: "mdi:clock-time-one-outline" },
    requested_brightness_level: {
        enabled_by_default: false,
        entity_category: "diagnostic",
        icon: "mdi:brightness-5",
    },
    requested_brightness_percent: {
        enabled_by_default: false,
        entity_category: "diagnostic",
        icon: "mdi:brightness-5",
    },
    smoke_density: { icon: "mdi:google-circles-communities", state_class: "measurement" },
    soil_moisture: { device_class: "moisture", state_class: "measurement" },
    temperature: { device_class: "temperature", state_class: "measurement" },
    temperature_probe: { device_class: "temperature", state_class: "measurement" },
    temperature_calibration: { entity_category: "config", icon: "mdi:wrench-clock" },
    temperature_max: { entity_category: "config", icon: "mdi:thermometer-plus" },
    temperature_min: { entity_category: "config", icon: "mdi:thermometer-minus" },
    temperature_offset: { icon: "mdi:thermometer-lines" },
    transition: { entity_category: "config", icon: "mdi:transition" },
    trigger_count: { icon: "mdi:counter", enabled_by_default: false, state_class: "measurement" },
    uv_index: { icon: "mdi:white-balance-sunny", state_class: "measurement" },
    voc: { device_class: "volatile_organic_compounds", state_class: "measurement" },
    voc_index: { state_class: "measurement", icon: "mdi:molecule" },
    voc_parts: { device_class: "volatile_organic_compounds_parts", state_class: "measurement" },
    vibration_timeout: { entity_category: "config", icon: "mdi:timer" },
    voltage: { device_class: "voltage", state_class: "measurement" },
    voltage_phase_b: { device_class: "voltage", state_class: "measurement" },
    voltage_phase_c: { device_class: "voltage", state_class: "measurement" },
    water_consumed: {
        device_class: "water",
        state_class: "total_increasing",
    },
    wind_chill: { device_class: "temperature", icon: "mdi:snowflake-thermometer", state_class: "measurement" },
    wind_direction: { icon: "mdi:compass-outline", state_class: "measurement" },
    wind_speed: { device_class: "wind_speed", icon: "mdi:weather-windy", state_class: "measurement" },
    x: { icon: "mdi:axis-x-arrow", state_class: "measurement" },
    x_axis: { icon: "mdi:axis-x-arrow", state_class: "measurement" },
    y: { icon: "mdi:axis-y-arrow", state_class: "measurement" },
    y_axis: { icon: "mdi:axis-y-arrow", state_class: "measurement" },
    z: { icon: "mdi:axis-z-arrow", state_class: "measurement" },
    z_axis: { icon: "mdi:axis-z-arrow", state_class: "measurement" },
};
const ENUM_DISCOVERY_LOOKUP = {
    action: { icon: "mdi:gesture-double-tap" },
    alarm_humidity: { entity_category: "config", icon: "mdi:water-percent-alert" },
    alarm_temperature: { entity_category: "config", icon: "mdi:thermometer-alert" },
    backlight_auto_dim: { entity_category: "config", icon: "mdi:brightness-auto" },
    backlight_mode: { entity_category: "config", icon: "mdi:lightbulb" },
    calibrate: { icon: "mdi:tune" },
    color_power_on_behavior: { entity_category: "config", icon: "mdi:palette" },
    control_mode: { entity_category: "config", icon: "mdi:tune" },
    device_mode: { entity_category: "config", icon: "mdi:tune" },
    effect: { enabled_by_default: false, icon: "mdi:palette" },
    force: { entity_category: "config", icon: "mdi:valve" },
    keep_time: { entity_category: "config", icon: "mdi:av-timer" },
    identify: { device_class: "identify" },
    keypad_lockout: { entity_category: "config", icon: "mdi:lock" },
    load_detection_mode: { entity_category: "config", icon: "mdi:tune" },
    load_dimmable: { entity_category: "config", icon: "mdi:chart-bell-curve" },
    load_type: { entity_category: "config", icon: "mdi:led-on" },
    melody: { entity_category: "config", icon: "mdi:music-note" },
    mode_phase_control: { entity_category: "config", icon: "mdi:tune" },
    mode: { entity_category: "config", icon: "mdi:tune" },
    mode_switch: { icon: "mdi:tune" },
    motion_sensitivity: { entity_category: "config", icon: "mdi:tune" },
    operation_mode: { entity_category: "config", icon: "mdi:tune" },
    power_on_behavior: { entity_category: "config", icon: "mdi:power-settings" },
    power_outage_memory: { entity_category: "config", icon: "mdi:power-settings" },
    power_supply_mode: { entity_category: "config", icon: "mdi:power-settings" },
    power_type: { entity_category: "config", icon: "mdi:lightning-bolt-circle" },
    restart: { device_class: "restart" },
    sensitivity: { entity_category: "config", icon: "mdi:tune" },
    sensor: { icon: "mdi:tune" },
    sensors_type: { entity_category: "config", icon: "mdi:tune" },
    sound_volume: { entity_category: "config", icon: "mdi:volume-high" },
    status: { icon: "mdi:state-machine" },
    switch_type: { entity_category: "config", icon: "mdi:tune" },
    temperature_display_mode: { entity_category: "config", icon: "mdi:thermometer" },
    temperature_sensor_select: { entity_category: "config", icon: "mdi:home-thermometer" },
    thermostat_unit: { entity_category: "config", icon: "mdi:thermometer" },
    update: { device_class: "update" },
    volume: { entity_category: "config", icon: "mdi: volume-high" },
    weather_condition: { icon: "mdi:weather-partly-cloudy" },
    week: { entity_category: "config", icon: "mdi:calendar-clock" },
};
const LIST_DISCOVERY_LOOKUP = {
    action: { icon: "mdi:gesture-double-tap" },
    color_options: { icon: "mdi:palette" },
    level_config: { entity_category: "diagnostic" },
    programming_mode: { icon: "mdi:calendar-clock" },
    schedule_settings: { icon: "mdi:calendar-clock" },
};
const featurePropertyWithoutEndpoint = (feature) => {
    if (feature.endpoint) {
        return feature.property.slice(0, -1 + -1 * feature.endpoint.length);
    }
    return feature.property;
};
/**
 * This class handles the bridge entity configuration for Home Assistant Discovery.
 */
class Bridge {
    coordinatorIeeeAddress;
    coordinatorType;
    coordinatorFirmwareVersion;
    discoveryEntries;
    options;
    // biome-ignore lint/style/useNamingConvention: API
    get ID() {
        return this.coordinatorIeeeAddress;
    }
    get name() {
        return "bridge";
    }
    get hardwareVersion() {
        return this.coordinatorType;
    }
    get firmwareVersion() {
        return this.coordinatorFirmwareVersion;
    }
    get configs() {
        return this.discoveryEntries;
    }
    constructor(ieeeAdress, version, discovery) {
        this.coordinatorIeeeAddress = ieeeAdress;
        this.coordinatorType = version.type;
        this.coordinatorFirmwareVersion = version.meta.revision ? `${version.meta.revision}` : /* v8 ignore next */ "";
        this.discoveryEntries = discovery;
        this.options = {
            ID: `bridge_${ieeeAdress}`,
            homeassistant: {
                name: "Zigbee2MQTT Bridge",
            },
        };
    }
    isDevice() {
        return false;
    }
    isGroup() {
        return false;
    }
}
/**
 * This extensions handles integration with HomeAssistant
 */
class HomeAssistant extends extension_1.default {
    discovered = {};
    discoveryTopic;
    discoveryRegex;
    discoveryRegexWoTopic = /(.*)\/(.*)\/(.*)\/config/;
    statusTopic;
    legacyActionSensor;
    experimentalEventEntities;
    // @ts-expect-error initialized in `start`
    zigbee2MQTTVersion;
    // @ts-expect-error initialized in `start`
    discoveryOrigin;
    // @ts-expect-error initialized in `start`
    bridge;
    // @ts-expect-error initialized in `start`
    bridgeIdentifier;
    actionValueTemplate;
    constructor(zigbee, mqtt, state, publishEntityState, eventBus, enableDisableExtension, restartCallback, addExtension) {
        super(zigbee, mqtt, state, publishEntityState, eventBus, enableDisableExtension, restartCallback, addExtension);
        if (settings.get().advanced.output === "attribute") {
            throw new Error("Home Assistant integration is not possible with attribute output!");
        }
        const haSettings = settings.get().homeassistant;
        (0, node_assert_1.default)(haSettings.enabled, `Home Assistant extension created with setting 'enabled: false'`);
        this.discoveryTopic = haSettings.discovery_topic;
        this.discoveryRegex = new RegExp(`${haSettings.discovery_topic}/(.*)/(.*)/(.*)/config`);
        this.statusTopic = haSettings.status_topic;
        this.legacyActionSensor = haSettings.legacy_action_sensor;
        this.experimentalEventEntities = haSettings.experimental_event_entities;
        if (haSettings.discovery_topic === settings.get().mqtt.base_topic) {
            throw new Error(`'homeassistant.discovery_topic' cannot not be equal to the 'mqtt.base_topic' (got '${settings.get().mqtt.base_topic}')`);
        }
        this.actionValueTemplate = this.getActionValueTemplate();
    }
    async start() {
        if (!settings.get().advanced.cache_state) {
            logger_1.default.warning("In order for Home Assistant integration to work properly set `cache_state: true");
        }
        this.zigbee2MQTTVersion = (await utils_1.default.getZigbee2MQTTVersion(false)).version;
        this.discoveryOrigin = { name: "Zigbee2MQTT", sw: this.zigbee2MQTTVersion, url: "https://www.zigbee2mqtt.io" };
        this.bridge = this.getBridgeEntity(await this.zigbee.getCoordinatorVersion());
        this.bridgeIdentifier = this.getDevicePayload(this.bridge).identifiers[0];
        this.eventBus.onEntityRemoved(this, this.onEntityRemoved);
        this.eventBus.onMQTTMessage(this, this.onMQTTMessage);
        this.eventBus.onEntityRenamed(this, this.onEntityRenamed);
        this.eventBus.onPublishEntityState(this, this.onPublishEntityState);
        this.eventBus.onGroupMembersChanged(this, this.onGroupMembersChanged);
        this.eventBus.onDeviceAnnounce(this, this.onZigbeeEvent);
        this.eventBus.onDeviceJoined(this, this.onZigbeeEvent);
        // TODO: this is triggering for any `data.status`?
        this.eventBus.onDeviceInterview(this, this.onZigbeeEvent);
        this.eventBus.onDeviceMessage(this, this.onZigbeeEvent);
        this.eventBus.onScenesChanged(this, this.onScenesChanged);
        this.eventBus.onEntityOptionsChanged(this, async (data) => await this.discover(data.entity));
        this.eventBus.onExposesChanged(this, async (data) => await this.discover(data.device));
        await this.mqtt.subscribe(this.statusTopic);
        /**
         * Prevent unnecessary re-discovery of entities by waiting 5 seconds for retained discovery messages to come in.
         * Any received discovery messages will not be published again.
         * Unsubscribe from the discoveryTopic to prevent receiving our own messages.
         */
        const discoverWait = 5;
        // Discover with `published = false`, this will populate `this.discovered` without publishing the discoveries.
        // This is needed for clearing outdated entries in `this.onMQTTMessage()`
        await this.discover(this.bridge, false);
        for (const e of this.zigbee.devicesAndGroupsIterator(utils_1.default.deviceNotCoordinator)) {
            await this.discover(e, false);
        }
        logger_1.default.debug(`Discovering entities to Home Assistant in ${discoverWait}s`);
        await this.mqtt.subscribe(`${this.discoveryTopic}/#`);
        setTimeout(async () => {
            await this.mqtt.unsubscribe(`${this.discoveryTopic}/#`);
            logger_1.default.debug("Discovering entities to Home Assistant");
            await this.discover(this.bridge);
            for (const e of this.zigbee.devicesAndGroupsIterator(utils_1.default.deviceNotCoordinator)) {
                await this.discover(e);
            }
        }, utils_1.default.seconds(discoverWait));
    }
    getDiscovered(entity) {
        const ID = typeof entity === "string" || typeof entity === "number" ? entity : entity.ID;
        if (!(ID in this.discovered)) {
            this.discovered[ID] = { messages: {}, triggers: new Set(), mockProperties: new Set(), discovered: false };
        }
        return this.discovered[ID];
    }
    exposeToConfig(exposes, entityType, allExposes, definition) {
        // For groups an array of exposes (of the same type) is passed, this is to determine e.g. what features
        // to use for a bulb (e.g. color_xy/color_temp)
        (0, node_assert_1.default)(entityType === "group" || exposes.length === 1, "Multiple exposes for device not allowed");
        const firstExpose = exposes[0];
        (0, node_assert_1.default)(entityType === "device" || GROUP_SUPPORTED_TYPES.includes(firstExpose.type), `Unsupported expose type ${firstExpose.type} for group`);
        const discoveryEntries = [];
        const endpoint = entityType === "device" ? exposes[0].endpoint : undefined;
        const getProperty = (feature) => (entityType === "group" ? featurePropertyWithoutEndpoint(feature) : feature.property);
        switch (firstExpose.type) {
            case "light": {
                const hasColorXY = exposes.find((expose) => expose.features.find((e) => e.name === "color_xy"));
                const hasColorHS = exposes.find((expose) => expose.features.find((e) => e.name === "color_hs"));
                const hasBrightness = exposes.find((expose) => expose.features.find((e) => e.name === "brightness"));
                const hasColorTemp = exposes.find((expose) => expose.features.find((e) => e.name === "color_temp"));
                const state = firstExpose.features.find((f) => f.name === "state");
                (0, node_assert_1.default)(state, `Light expose must have a 'state'`);
                // Prefer HS over XY when at least one of the lights in the group prefers HS over XY.
                // A light prefers HS over XY when HS is earlier in the feature array than HS.
                const preferHS = exposes
                    .map((e) => [e.features.findIndex((ee) => ee.name === "color_xy"), e.features.findIndex((ee) => ee.name === "color_hs")])
                    .filter((d) => d[0] !== -1 && d[1] !== -1 && d[1] < d[0]).length !== 0;
                const discoveryEntry = {
                    type: "light",
                    object_id: endpoint ? `light_${endpoint}` : "light",
                    mockProperties: [{ property: state.property, value: null }],
                    discovery_payload: {
                        name: endpoint ? utils_1.default.capitalize(endpoint) : null,
                        brightness: !!hasBrightness,
                        schema: "json",
                        command_topic: true,
                        brightness_scale: 254,
                        command_topic_prefix: endpoint,
                        state_topic_postfix: endpoint,
                    },
                };
                const colorModes = [
                    hasColorXY && !preferHS ? "xy" : null,
                    (!hasColorXY || preferHS) && hasColorHS ? "hs" : null,
                    hasColorTemp ? "color_temp" : null,
                ].filter((c) => c);
                if (colorModes.length) {
                    discoveryEntry.discovery_payload.supported_color_modes = colorModes;
                }
                else {
                    /**
                     * All bulbs support brightness, note that `brightness` cannot be combined
                     * with other color modes.
                     * https://github.com/Koenkk/zigbee2mqtt/issues/26520#issuecomment-2692432058
                     */
                    discoveryEntry.discovery_payload.supported_color_modes = ["brightness"];
                }
                if (hasColorTemp) {
                    const colorTemps = exposes
                        .map((expose) => expose.features.find((e) => e.name === "color_temp"))
                        .filter((e) => e !== undefined && (0, utils_1.isNumericExpose)(e));
                    const max = Math.min(...colorTemps.map((e) => e.value_max).filter((e) => e !== undefined));
                    const min = Math.max(...colorTemps.map((e) => e.value_min).filter((e) => e !== undefined));
                    discoveryEntry.discovery_payload.max_mireds = max;
                    discoveryEntry.discovery_payload.min_mireds = min;
                }
                const effects = utils_1.default.arrayUnique(utils_1.default.flatten(allExposes
                    .filter(utils_1.isEnumExpose)
                    .filter((e) => e.name === "effect")
                    .map((e) => e.values)));
                if (effects.length) {
                    discoveryEntry.discovery_payload.effect = true;
                    discoveryEntry.discovery_payload.effect_list = effects;
                }
                discoveryEntries.push(discoveryEntry);
                break;
            }
            case "switch": {
                const state = firstExpose.features.filter(utils_1.isBinaryExpose).find((f) => f.name === "state");
                (0, node_assert_1.default)(state, `Switch expose must have a 'state'`);
                const property = getProperty(state);
                const discoveryEntry = {
                    type: "switch",
                    object_id: endpoint ? `switch_${endpoint}` : "switch",
                    mockProperties: [{ property: property, value: null }],
                    discovery_payload: {
                        name: endpoint ? utils_1.default.capitalize(endpoint) : null,
                        payload_off: state.value_off,
                        payload_on: state.value_on,
                        value_template: `{{ value_json.${property} }}`,
                        command_topic: true,
                        command_topic_prefix: endpoint,
                    },
                };
                if (SWITCH_DIFFERENT.includes(property)) {
                    discoveryEntry.discovery_payload.name = firstExpose.label;
                    discoveryEntry.discovery_payload.command_topic_postfix = property;
                    discoveryEntry.discovery_payload.state_off = state.value_off;
                    discoveryEntry.discovery_payload.state_on = state.value_on;
                    discoveryEntry.object_id = property;
                    if (property === "window_detection") {
                        discoveryEntry.discovery_payload.icon = "mdi:window-open-variant";
                    }
                }
                discoveryEntries.push(discoveryEntry);
                break;
            }
            case "climate": {
                const setpointProperties = ["occupied_heating_setpoint", "current_heating_setpoint"];
                const setpoint = firstExpose.features.filter(utils_1.isNumericExpose).find((f) => setpointProperties.includes(f.name));
                (0, node_assert_1.default)(setpoint && setpoint.value_min !== undefined && setpoint.value_max !== undefined, "No setpoint found or it is missing value_min/max");
                const temperature = firstExpose.features.find((f) => f.name === "local_temperature");
                (0, node_assert_1.default)(temperature, "No temperature found");
                const discoveryEntry = {
                    type: "climate",
                    object_id: endpoint ? `climate_${endpoint}` : "climate",
                    mockProperties: [],
                    discovery_payload: {
                        name: endpoint ? utils_1.default.capitalize(endpoint) : null,
                        // Static
                        state_topic: false,
                        temperature_unit: "C",
                        // Setpoint
                        temp_step: setpoint.value_step,
                        min_temp: setpoint.value_min.toString(),
                        max_temp: setpoint.value_max.toString(),
                        // Temperature
                        current_temperature_topic: true,
                        current_temperature_template: `{{ value_json.${temperature.property} }}`,
                        command_topic_prefix: endpoint,
                    },
                };
                const mode = firstExpose.features.filter(utils_1.isEnumExpose).find((f) => f.name === "system_mode");
                if (mode) {
                    if (mode.values.includes("sleep")) {
                        // 'sleep' is not supported by Home Assistant, but is valid according to ZCL
                        // TRV that support sleep (e.g. Viessmann) will have it removed from here,
                        // this allows other expose consumers to still use it, e.g. the frontend.
                        mode.values.splice(mode.values.indexOf("sleep"), 1);
                    }
                    discoveryEntry.discovery_payload.mode_state_topic = true;
                    discoveryEntry.discovery_payload.mode_state_template = `{{ value_json.${mode.property} }}`;
                    discoveryEntry.discovery_payload.modes = mode.values;
                    discoveryEntry.discovery_payload.mode_command_topic = true;
                }
                const state = firstExpose.features.find((f) => f.name === "running_state");
                if (state) {
                    discoveryEntry.mockProperties.push({ property: state.property, value: null });
                    discoveryEntry.discovery_payload.action_topic = true;
                    discoveryEntry.discovery_payload.action_template = `{% set values = {None:None,'idle':'idle','heat':'heating','cool':'cooling','fan_only':'fan'} %}{{ values[value_json.${state.property}] }}`;
                }
                const coolingSetpoint = firstExpose.features.find((f) => f.name === "occupied_cooling_setpoint");
                if (coolingSetpoint) {
                    discoveryEntry.discovery_payload.temperature_low_command_topic = setpoint.name;
                    discoveryEntry.discovery_payload.temperature_low_state_template = `{{ value_json.${setpoint.property} }}`;
                    discoveryEntry.discovery_payload.temperature_low_state_topic = true;
                    discoveryEntry.discovery_payload.temperature_high_command_topic = coolingSetpoint.name;
                    discoveryEntry.discovery_payload.temperature_high_state_template = `{{ value_json.${coolingSetpoint.property} }}`;
                    discoveryEntry.discovery_payload.temperature_high_state_topic = true;
                }
                else {
                    discoveryEntry.discovery_payload.temperature_command_topic = setpoint.name;
                    discoveryEntry.discovery_payload.temperature_state_template = `{{ value_json.${setpoint.property} }}`;
                    discoveryEntry.discovery_payload.temperature_state_topic = true;
                }
                const fanMode = firstExpose.features.filter(utils_1.isEnumExpose).find((f) => f.name === "fan_mode");
                if (fanMode) {
                    discoveryEntry.discovery_payload.fan_modes = fanMode.values;
                    discoveryEntry.discovery_payload.fan_mode_command_topic = true;
                    discoveryEntry.discovery_payload.fan_mode_state_template = `{{ value_json.${fanMode.property} }}`;
                    discoveryEntry.discovery_payload.fan_mode_state_topic = true;
                }
                const swingMode = firstExpose.features.filter(utils_1.isEnumExpose).find((f) => f.name === "swing_mode");
                if (swingMode) {
                    discoveryEntry.discovery_payload.swing_modes = swingMode.values;
                    discoveryEntry.discovery_payload.swing_mode_command_topic = true;
                    discoveryEntry.discovery_payload.swing_mode_state_template = `{{ value_json.${swingMode.property} }}`;
                    discoveryEntry.discovery_payload.swing_mode_state_topic = true;
                }
                const preset = firstExpose.features.filter(utils_1.isEnumExpose).find((f) => f.name === "preset");
                if (preset) {
                    discoveryEntry.discovery_payload.preset_modes = preset.values;
                    discoveryEntry.discovery_payload.preset_mode_command_topic = "preset";
                    discoveryEntry.discovery_payload.preset_mode_value_template = `{{ value_json.${preset.property} }}`;
                    discoveryEntry.discovery_payload.preset_mode_state_topic = true;
                }
                const tempCalibration = firstExpose.features
                    .filter(utils_1.isNumericExpose)
                    .find((f) => f.name === "local_temperature_calibration");
                if (tempCalibration) {
                    const discoveryEntry = {
                        type: "number",
                        object_id: endpoint ? `${tempCalibration.name}_${endpoint}` : `${tempCalibration.name}`,
                        mockProperties: [{ property: tempCalibration.property, value: null }],
                        discovery_payload: {
                            name: endpoint ? `${tempCalibration.label} ${endpoint}` : tempCalibration.label,
                            value_template: `{{ value_json.${tempCalibration.property} }}`,
                            command_topic: true,
                            command_topic_prefix: endpoint,
                            command_topic_postfix: tempCalibration.property,
                            device_class: "temperature_delta",
                            entity_category: "config",
                            ...(tempCalibration.unit && { unit_of_measurement: tempCalibration.unit }),
                        },
                    };
                    if (tempCalibration.value_min != null)
                        discoveryEntry.discovery_payload.min = tempCalibration.value_min;
                    if (tempCalibration.value_max != null)
                        discoveryEntry.discovery_payload.max = tempCalibration.value_max;
                    if (tempCalibration.value_step != null) {
                        discoveryEntry.discovery_payload.step = tempCalibration.value_step;
                    }
                    discoveryEntries.push(discoveryEntry);
                }
                const piHeatingDemand = firstExpose.features.filter(utils_1.isNumericExpose).find((f) => f.name === "pi_heating_demand");
                if (piHeatingDemand) {
                    const discoveryEntry = {
                        object_id: endpoint ? `${piHeatingDemand.name}_${endpoint}` : `${piHeatingDemand.name}`,
                        mockProperties: [{ property: piHeatingDemand.property, value: null }],
                        discovery_payload: {
                            name: endpoint ? `${piHeatingDemand.label} ${endpoint}` : piHeatingDemand.label,
                            value_template: `{{ value_json.${piHeatingDemand.property} }}`,
                            ...(piHeatingDemand.unit && { unit_of_measurement: piHeatingDemand.unit }),
                            icon: "mdi:radiator",
                        },
                    };
                    (0, node_assert_1.default)(discoveryEntry.discovery_payload);
                    if (piHeatingDemand.access & ACCESS_SET) {
                        discoveryEntry.type = "number";
                        discoveryEntry.discovery_payload.command_topic = true;
                        discoveryEntry.discovery_payload.command_topic_prefix = endpoint;
                        discoveryEntry.discovery_payload.command_topic_postfix = piHeatingDemand.property;
                        discoveryEntry.discovery_payload.min = piHeatingDemand.value_min;
                        discoveryEntry.discovery_payload.max = piHeatingDemand.value_max;
                    }
                    else {
                        discoveryEntry.type = "sensor";
                        discoveryEntry.discovery_payload.entity_category = "diagnostic";
                    }
                    discoveryEntries.push(discoveryEntry);
                }
                const piCoolingDemand = firstExpose.features.filter(utils_1.isNumericExpose).find((f) => f.name === "pi_cooling_demand");
                if (piCoolingDemand) {
                    const discoveryEntry = {
                        type: "sensor",
                        object_id: endpoint ? /* v8 ignore next */ `${piCoolingDemand.name}_${endpoint}` : `${piCoolingDemand.name}`,
                        mockProperties: [{ property: piCoolingDemand.property, value: null }],
                        discovery_payload: {
                            name: endpoint ? /* v8 ignore next */ `${piCoolingDemand.label} ${endpoint}` : piCoolingDemand.label,
                            value_template: `{{ value_json.${piCoolingDemand.property} }}`,
                            ...(piCoolingDemand.unit && { unit_of_measurement: piCoolingDemand.unit }),
                            entity_category: "diagnostic",
                            icon: "mdi:air-conditioner",
                        },
                    };
                    discoveryEntries.push(discoveryEntry);
                }
                const localTemperature = firstExpose.features.filter(utils_1.isNumericExpose).find((f) => f.name === "local_temperature");
                const temperatureSensor = allExposes?.filter(utils_1.isNumericExpose).find((e) => e.name === "temperature" && e.access & ACCESS_STATE);
                const localTemperatureSensor = allExposes
                    ?.filter(utils_1.isNumericExpose)
                    .find((e) => e.name === "local_temperature" && e.access & ACCESS_STATE);
                if (localTemperature && !temperatureSensor && !localTemperatureSensor) {
                    const discoveryEntry = {
                        type: "sensor",
                        object_id: endpoint ? `${localTemperature.name}_${endpoint}` : `${localTemperature.name}`,
                        mockProperties: [{ property: localTemperature.property, value: null }],
                        discovery_payload: {
                            name: endpoint ? `${localTemperature.label} ${endpoint}` : localTemperature.label,
                            value_template: `{{ value_json.${localTemperature.property} }}`,
                            ...(localTemperature.unit && { unit_of_measurement: localTemperature.unit }),
                            device_class: "temperature",
                            state_class: "measurement",
                        },
                    };
                    discoveryEntries.push(discoveryEntry);
                }
                const currentHumidity = allExposes?.filter(utils_1.isNumericExpose).find((e) => e.name === "humidity" && e.access & ACCESS_STATE);
                if (currentHumidity) {
                    discoveryEntry.discovery_payload.current_humidity_template = `{{ value_json.${currentHumidity.property} }}`;
                    discoveryEntry.discovery_payload.current_humidity_topic = true;
                }
                discoveryEntries.push(discoveryEntry);
                break;
            }
            case "lock": {
                const state = firstExpose.features.filter(utils_1.isBinaryExpose).find((f) => f.name === "state");
                (0, node_assert_1.default)(state?.name === "state", "Lock expose must have a 'state'");
                const discoveryEntry = {
                    type: "lock",
                    /* v8 ignore next */
                    object_id: endpoint ? `lock_${endpoint}` : "lock",
                    mockProperties: [{ property: state.property, value: null }],
                    discovery_payload: {
                        /* v8 ignore next */
                        name: endpoint ? utils_1.default.capitalize(endpoint) : null,
                        command_topic_prefix: endpoint,
                        command_topic: true,
                        value_template: `{{ value_json.${state.property} }}`,
                        state_locked: state.value_on,
                        state_unlocked: state.value_off,
                        /* v8 ignore next */
                        command_topic_postfix: endpoint ? state.property : null,
                    },
                };
                discoveryEntries.push(discoveryEntry);
                break;
            }
            case "cover": {
                const state = exposes
                    .find((expose) => expose.features.find((e) => e.name === "state"))
                    ?.features.find((f) => f.name === "state");
                (0, node_assert_1.default)(state, `Cover expose must have a 'state'`);
                const position = exposes
                    .find((expose) => expose.features.find((e) => e.name === "position"))
                    ?.features.find((f) => f.name === "position");
                const tilt = exposes
                    .find((expose) => expose.features.find((e) => e.name === "tilt"))
                    ?.features.find((f) => f.name === "tilt");
                const motorState = allExposes
                    ?.filter(utils_1.isEnumExpose)
                    .find((e) => ["motor_state", "moving"].includes(e.name) && e.access === ACCESS_STATE);
                const running = allExposes?.filter(utils_1.isBinaryExpose)?.find((e) => e.name === "running");
                const discoveryEntry = {
                    type: "cover",
                    mockProperties: [{ property: state.property, value: null }],
                    object_id: endpoint ? `cover_${endpoint}` : "cover",
                    discovery_payload: {
                        name: endpoint ? utils_1.default.capitalize(endpoint) : null,
                        command_topic_prefix: endpoint,
                        command_topic: true,
                        state_topic: true,
                        state_topic_postfix: endpoint,
                    },
                };
                // If curtains have `running` property, use this in discovery.
                // The movement direction is calculated (assumed) in this case.
                if (running) {
                    (0, node_assert_1.default)(position, `Cover must have 'position' when it has 'running'`);
                    discoveryEntry.discovery_payload.value_template = `{% if "${featurePropertyWithoutEndpoint(running)}" in value_json and value_json.${featurePropertyWithoutEndpoint(running)} %} {% if value_json.${featurePropertyWithoutEndpoint(position)} > 0 %} closing {% else %} opening {% endif %} {% else %} stopped {% endif %}`;
                }
                // If curtains have `motor_state` or `moving` property, lookup for possible
                // state names to detect movement direction and use this in discovery.
                if (motorState) {
                    const openingState = motorState.values.find((s) => COVER_OPENING_LOOKUP.includes(s.toString().toLowerCase()));
                    const closingState = motorState.values.find((s) => COVER_CLOSING_LOOKUP.includes(s.toString().toLowerCase()));
                    const stoppedState = motorState.values.find((s) => COVER_STOPPED_LOOKUP.includes(s.toString().toLowerCase()));
                    if (openingState && closingState && stoppedState) {
                        discoveryEntry.discovery_payload.state_opening = openingState;
                        discoveryEntry.discovery_payload.state_closing = closingState;
                        discoveryEntry.discovery_payload.state_stopped = stoppedState;
                        discoveryEntry.discovery_payload.value_template = `{% if "${featurePropertyWithoutEndpoint(motorState)}" in value_json and value_json.${featurePropertyWithoutEndpoint(motorState)} %} {{ value_json.${featurePropertyWithoutEndpoint(motorState)} }} {% else %} ${stoppedState} {% endif %}`;
                    }
                }
                // If curtains do not have `running`, `motor_state` or `moving` properties.
                if (!discoveryEntry.discovery_payload.value_template) {
                    discoveryEntry.discovery_payload.value_template = `{{ value_json.${featurePropertyWithoutEndpoint(state)} }}`;
                    discoveryEntry.discovery_payload.state_open = "OPEN";
                    discoveryEntry.discovery_payload.state_closed = "CLOSE";
                    discoveryEntry.discovery_payload.state_stopped = "STOP";
                }
                /* v8 ignore start */
                if (!position && !tilt) {
                    discoveryEntry.discovery_payload.optimistic = true;
                }
                /* v8 ignore stop */
                if (position) {
                    discoveryEntry.discovery_payload = {
                        ...discoveryEntry.discovery_payload,
                        position_template: `{{ value_json.${featurePropertyWithoutEndpoint(position)} }}`,
                        set_position_template: `{ "${getProperty(position)}": {{ position }} }`,
                        set_position_topic: true,
                        position_topic: true,
                    };
                }
                if (tilt) {
                    discoveryEntry.discovery_payload = {
                        ...discoveryEntry.discovery_payload,
                        tilt_command_topic: true,
                        tilt_status_topic: true,
                        tilt_status_template: `{{ value_json.${featurePropertyWithoutEndpoint(tilt)} }}`,
                    };
                }
                discoveryEntries.push(discoveryEntry);
                break;
            }
            case "fan": {
                (0, node_assert_1.default)(!endpoint, "Endpoint not supported for fan type");
                const discoveryEntry = {
                    type: "fan",
                    object_id: "fan",
                    mockProperties: [{ property: "fan_state", value: null }],
                    discovery_payload: {
                        name: null,
                        state_topic: true,
                        command_topic: true,
                    },
                };
                const modeEmulatedSpeed = firstExpose.features.filter(utils_1.isEnumExpose).find((e) => e.name === "mode");
                const nativeSpeed = firstExpose.features.filter(utils_1.isNumericExpose).find((e) => e.name === "speed");
                // Exactly one mode needs to be active (logical xor)
                (0, node_assert_1.default)(!modeEmulatedSpeed !== !nativeSpeed, "Fans need to be either mode- or speed-controlled");
                if (modeEmulatedSpeed) {
                    // A fan entity in Home Assistant 2021.3 and above may have a speed,
                    // controlled by a percentage from 1 to 100, and/or non-speed presets.
                    // The MQTT Fan integration allows the speed percentage to be mapped
                    // to a narrower range of speeds (e.g. 1-3), and for these speeds to be
                    // translated to and from MQTT messages via templates.
                    //
                    // For the fixed fan modes in ZCL hvacFanCtrl, we model speeds "low",
                    // "medium", and "high" as three speeds covering the full percentage
                    // range as done in Home Assistant's zigpy fan integration, plus
                    // presets "on", "auto" and "smart" to cover the remaining modes in
                    // ZCL. This supports a generic ZCL HVAC Fan Control fan. "Off" is
                    // always a valid speed.
                    let speeds = ["off"].concat(["low", "medium", "high", "1", "2", "3", "4", "5", "6", "7", "8", "9"].filter((s) => modeEmulatedSpeed.values.includes(s)));
                    let presets = ["on", "auto", "smart"].filter((s) => modeEmulatedSpeed.values.includes(s));
                    if (definition?.model === "99432") {
                        // The Hampton Bay 99432 fan implements 4 speeds using the ZCL
                        // hvacFanCtrl values `low`, `medium`, `high`, and `on`, and
                        // 1 preset called "Comfort Breeze" using the ZCL value `smart`.
                        // ZCL value `auto` is unused.
                        speeds = ["off", "low", "medium", "high", "on"];
                        presets = ["smart"];
                    }
                    const allowed = [...speeds, ...presets];
                    for (const val of modeEmulatedSpeed.values) {
                        (0, node_assert_1.default)(allowed.includes(val.toString()));
                    }
                    const percentValues = speeds.map((s, i) => `'${s}':${i}`).join(", ");
                    const percentCommands = speeds.map((s, i) => `${i}:'${s}'`).join(", ");
                    const presetList = presets.map((s) => `'${s}'`).join(", ");
                    discoveryEntry.discovery_payload.percentage_state_topic = true;
                    discoveryEntry.discovery_payload.percentage_command_topic = "fan_mode";
                    discoveryEntry.discovery_payload.percentage_value_template = `{{ {${percentValues}}[value_json.${modeEmulatedSpeed.property}] | default('None') }}`;
                    discoveryEntry.discovery_payload.percentage_command_template = `{{ {${percentCommands}}[value] | default('') }}`;
                    discoveryEntry.discovery_payload.speed_range_min = 1;
                    discoveryEntry.discovery_payload.speed_range_max = speeds.length - 1;
                    (0, node_assert_1.default)(presets.length !== 0);
                    discoveryEntry.discovery_payload.preset_mode_state_topic = true;
                    discoveryEntry.discovery_payload.preset_mode_command_topic = "fan_mode";
                    discoveryEntry.discovery_payload.preset_mode_value_template = `{{ value_json.${modeEmulatedSpeed.property} if value_json.${modeEmulatedSpeed.property} in [${presetList}] else 'None' | default('None') }}`;
                    discoveryEntry.discovery_payload.preset_modes = presets;
                    // Emulate state based on mode
                    discoveryEntry.discovery_payload.state_value_template = "{{ value_json.fan_state }}";
                    discoveryEntry.discovery_payload.command_topic_postfix = "fan_state";
                }
                else if (nativeSpeed) {
                    discoveryEntry.discovery_payload.percentage_state_topic = true;
                    discoveryEntry.discovery_payload.percentage_command_topic = "speed";
                    discoveryEntry.discovery_payload.percentage_value_template = `{{ value_json.${nativeSpeed.property} | default('None') }}`;
                    discoveryEntry.discovery_payload.percentage_command_template = `{{ value | default('') }}`;
                    discoveryEntry.discovery_payload.speed_range_min = nativeSpeed.value_min;
                    discoveryEntry.discovery_payload.speed_range_max = nativeSpeed.value_max;
                    // Speed-controlled fans generally have an onOff cluster, use that for state
                    discoveryEntry.discovery_payload.state_value_template = "{{ value_json.state }}";
                    discoveryEntry.discovery_payload.command_topic_postfix = "state";
                }
                discoveryEntries.push(discoveryEntry);
                break;
            }
            case "binary": {
                /**
                 * If Z2M binary attribute has SET access then expose it as `switch` in HA
                 * There is also a check on the values for typeof boolean to prevent invalid values and commands
                 * silently failing - commands work fine but some devices won't reject unexpected values.
                 * https://github.com/Koenkk/zigbee2mqtt/issues/7740
                 */
                (0, utils_1.assertBinaryExpose)(firstExpose);
                if (firstExpose.access & ACCESS_SET) {
                    const discoveryEntry = {
                        type: "switch",
                        mockProperties: [{ property: firstExpose.property, value: null }],
                        object_id: endpoint ? `switch_${firstExpose.name}_${endpoint}` : `switch_${firstExpose.name}`,
                        discovery_payload: {
                            name: endpoint ? /* v8 ignore next */ `${firstExpose.label} ${endpoint}` : firstExpose.label,
                            value_template: typeof firstExpose.value_on === "boolean"
                                ? `{% if value_json.${firstExpose.property} %}true{% else %}false{% endif %}`
                                : `{{ value_json.${firstExpose.property} }}`,
                            payload_on: firstExpose.value_on.toString(),
                            payload_off: firstExpose.value_off.toString(),
                            command_topic: true,
                            command_topic_prefix: endpoint,
                            command_topic_postfix: firstExpose.property,
                            ...(BINARY_DISCOVERY_LOOKUP[firstExpose.name] || {}),
                        },
                    };
                    discoveryEntries.push(discoveryEntry);
                }
                else {
                    const discoveryEntry = {
                        type: "binary_sensor",
                        object_id: endpoint ? `${firstExpose.name}_${endpoint}` : `${firstExpose.name}`,
                        mockProperties: [{ property: firstExpose.property, value: null }],
                        discovery_payload: {
                            name: endpoint ? /* v8 ignore next */ `${firstExpose.label} ${endpoint}` : firstExpose.label,
                            value_template: `{{ value_json.${firstExpose.property} }}`,
                            payload_on: firstExpose.value_on,
                            payload_off: firstExpose.value_off,
                            ...(BINARY_DISCOVERY_LOOKUP[firstExpose.name] || {}),
                        },
                    };
                    discoveryEntries.push(discoveryEntry);
                }
                break;
            }
            case "numeric": {
                (0, utils_1.assertNumericExpose)(firstExpose);
                const allowsSet = firstExpose.access & ACCESS_SET;
                /**
                 * If numeric attribute has SET access then expose as SELECT entity.
                 */
                if (allowsSet) {
                    const discoveryEntry = {
                        type: "number",
                        object_id: endpoint ? `${firstExpose.name}_${endpoint}` : `${firstExpose.name}`,
                        mockProperties: [{ property: firstExpose.property, value: null }],
                        discovery_payload: {
                            name: endpoint ? `${firstExpose.label} ${endpoint}` : firstExpose.label,
                            value_template: `{{ value_json.${firstExpose.property} }}`,
                            command_topic: true,
                            command_topic_prefix: endpoint,
                            command_topic_postfix: firstExpose.property,
                            ...(firstExpose.unit && { unit_of_measurement: firstExpose.unit }),
                            ...(firstExpose.value_step && { step: firstExpose.value_step }),
                            ...NUMERIC_DISCOVERY_LOOKUP[firstExpose.name],
                        },
                    };
                    if (NUMERIC_DISCOVERY_LOOKUP[firstExpose.name]?.device_class === "temperature") {
                        discoveryEntry.discovery_payload.device_class = NUMERIC_DISCOVERY_LOOKUP[firstExpose.name]?.device_class;
                    }
                    else {
                        delete discoveryEntry.discovery_payload.device_class;
                    }
                    if (firstExpose.value_min != null)
                        discoveryEntry.discovery_payload.min = firstExpose.value_min;
                    if (firstExpose.value_max != null)
                        discoveryEntry.discovery_payload.max = firstExpose.value_max;
                    discoveryEntries.push(discoveryEntry);
                    break;
                }
                const extraAttrs = {};
                // If a variable includes Wh, mark it as energy
                if (firstExpose.unit && ["Wh", "kWh"].includes(firstExpose.unit)) {
                    Object.assign(extraAttrs, { device_class: "energy", state_class: "total_increasing" });
                }
                // If a variable includes A or mA, mark it as current
                else if (firstExpose.unit && ["A", "mA"].includes(firstExpose.unit)) {
                    Object.assign(extraAttrs, { device_class: "current", state_class: "measurement" });
                }
                // If a variable includes mW, W, kW mark it as power
                else if (firstExpose.unit && ["mW", "W", "kW"].includes(firstExpose.unit)) {
                    Object.assign(extraAttrs, { device_class: "power", state_class: "measurement" });
                }
                let key = firstExpose.name;
                // Home Assistant uses a different voc device_class for µg/m³ versus ppb or ppm.
                if (firstExpose.name === "voc" && firstExpose.unit && ["ppb", "ppm"].includes(firstExpose.unit)) {
                    key = "voc_parts";
                }
                const discoveryEntry = {
                    type: "sensor",
                    object_id: endpoint ? `${firstExpose.name}_${endpoint}` : `${firstExpose.name}`,
                    mockProperties: [{ property: firstExpose.property, value: null }],
                    discovery_payload: {
                        name: endpoint ? `${firstExpose.label} ${endpoint}` : firstExpose.label,
                        value_template: `{{ value_json.${firstExpose.property} }}`,
                        enabled_by_default: !allowsSet,
                        ...(firstExpose.unit && { unit_of_measurement: firstExpose.unit }),
                        ...NUMERIC_DISCOVERY_LOOKUP[key],
                        ...extraAttrs,
                    },
                };
                // When a device_class is set, unit_of_measurement must be set, otherwise warnings are generated.
                // https://github.com/Koenkk/zigbee2mqtt/issues/15958#issuecomment-1377483202
                if (discoveryEntry.discovery_payload.device_class && !discoveryEntry.discovery_payload.unit_of_measurement) {
                    delete discoveryEntry.discovery_payload.device_class;
                }
                // entity_category config is not allowed for sensors
                // https://github.com/Koenkk/zigbee2mqtt/issues/20252
                if (discoveryEntry.discovery_payload.entity_category === "config") {
                    discoveryEntry.discovery_payload.entity_category = "diagnostic";
                }
                discoveryEntries.push(discoveryEntry);
                break;
            }
            case "enum": {
                (0, utils_1.assertEnumExpose)(firstExpose);
                /**
                 * If enum attribute does not have SET access and is named 'action', then expose
                 * as EVENT entity. Wildcard actions like `recall_*` are currently not supported.
                 */
                if (firstExpose.property === "action") {
                    if (this.experimentalEventEntities &&
                        firstExpose.access & ACCESS_STATE &&
                        !(firstExpose.access & ACCESS_SET) &&
                        firstExpose.property === "action") {
                        discoveryEntries.push({
                            type: "event",
                            object_id: firstExpose.property,
                            mockProperties: [],
                            discovery_payload: {
                                name: endpoint ? /* v8 ignore next */ `${firstExpose.label} ${endpoint}` : firstExpose.label,
                                state_topic: true,
                                event_types: this.prepareActionEventTypes(firstExpose.values),
                                value_template: this.actionValueTemplate,
                                ...ENUM_DISCOVERY_LOOKUP[firstExpose.name],
                            },
                        });
                    }
                    if (!this.legacyActionSensor) {
                        break;
                    }
                }
                const valueTemplate = firstExpose.access & ACCESS_STATE ? `{{ value_json.${firstExpose.property} }}` : undefined;
                /**
                 * If enum has only one item and has SET access then expose as BUTTON entity.
                 */
                if (firstExpose.access & ACCESS_SET && firstExpose.values.length === 1) {
                    discoveryEntries.push({
                        type: "button",
                        object_id: firstExpose.property,
                        mockProperties: [{ property: firstExpose.property, value: null }],
                        discovery_payload: {
                            name: endpoint ? /* v8 ignore next */ `${firstExpose.label} ${endpoint}` : firstExpose.label,
                            state_topic: false,
                            command_topic_prefix: endpoint,
                            command_topic: true,
                            command_topic_postfix: firstExpose.property,
                            payload_press: firstExpose.values[0].toString(),
                            ...ENUM_DISCOVERY_LOOKUP[firstExpose.name],
                        },
                    });
                    break;
                }
                /**
                 * If enum attribute has SET access then expose as SELECT entity.
                 */
                if (firstExpose.access & ACCESS_SET) {
                    discoveryEntries.push({
                        type: "select",
                        object_id: firstExpose.property,
                        mockProperties: [{ property: firstExpose.property, value: null }],
                        discovery_payload: {
                            name: endpoint ? `${firstExpose.label} ${endpoint}` : firstExpose.label,
                            value_template: valueTemplate,
                            state_topic: !!(firstExpose.access & ACCESS_STATE),
                            command_topic_prefix: endpoint,
                            command_topic: true,
                            command_topic_postfix: firstExpose.property,
                            options: firstExpose.values.map((v) => v.toString()),
                            ...ENUM_DISCOVERY_LOOKUP[firstExpose.name],
                        },
                    });
                    break;
                }
                /**
                 * Otherwise expose as SENSOR entity.
                 */
                if (firstExpose.access & ACCESS_STATE) {
                    discoveryEntries.push({
                        type: "sensor",
                        object_id: firstExpose.property,
                        mockProperties: [{ property: firstExpose.property, value: null }],
                        discovery_payload: {
                            name: endpoint ? `${firstExpose.label} ${endpoint}` : firstExpose.label,
                            value_template: valueTemplate,
                            ...ENUM_DISCOVERY_LOOKUP[firstExpose.name],
                        },
                    });
                }
                break;
            }
            case "text":
            case "composite":
            case "list": {
                const firstExposeTyped = firstExpose;
                // Warning composite → HA siren entity
                if (firstExposeTyped.type === "composite" && firstExposeTyped.name === "warning" && firstExposeTyped.access & ACCESS_SET) {
                    const warningExpose = firstExpose;
                    const modeFeature = warningExpose.features.filter(utils_1.isEnumExpose).find((f) => f.name === "mode");
                    const levelFeature = warningExpose.features.filter(utils_1.isEnumExpose).find((f) => f.name === "level");
                    const durationFeature = warningExpose.features.filter(utils_1.isNumericExpose).find((f) => f.name === "duration");
                    const discoveryEntry = {
                        type: "siren",
                        object_id: endpoint ? /* v8 ignore next */ `siren_${endpoint}` : "siren",
                        mockProperties: [{ property: warningExpose.property, value: null }],
                        discovery_payload: {
                            name: endpoint ? /* v8 ignore next */ utils_1.default.capitalize(endpoint) : null,
                            command_topic: true,
                            command_topic_prefix: endpoint,
                            state_topic: false,
                            optimistic: true,
                        },
                    };
                    if (modeFeature) {
                        const tones = modeFeature.values.filter((v) => v !== "stop");
                        if (tones.length) {
                            discoveryEntry.discovery_payload.available_tones = tones;
                        }
                    }
                    if (levelFeature) {
                        discoveryEntry.discovery_payload.support_volume_set = true;
                    }
                    if (durationFeature) {
                        discoveryEntry.discovery_payload.support_duration = true;
                    }
                    const levelTemplate = "{% if volume_level is defined %}" +
                        "{% if volume_level | float <= 0.25 %}low" +
                        "{% elif volume_level | float <= 0.5 %}medium" +
                        "{% elif volume_level | float <= 0.75 %}high" +
                        "{% else %}very_high{% endif %}" +
                        "{% else %}medium{% endif %}";
                    discoveryEntry.discovery_payload.command_template =
                        `{"warning": {"mode": "{{ tone | default('emergency') }}", ` +
                            `"level": "${levelTemplate}", ` +
                            `"duration": {{ duration | default(10) }}}}`;
                    discoveryEntry.discovery_payload.command_off_template = '{"warning": {"mode": "stop"}}';
                    discoveryEntries.push(discoveryEntry);
                    break;
                }
                if (firstExposeTyped.type === "text" && firstExposeTyped.access & ACCESS_SET) {
                    discoveryEntries.push({
                        type: "text",
                        object_id: firstExposeTyped.property,
                        mockProperties: [{ property: firstExposeTyped.property, value: null }],
                        discovery_payload: {
                            name: endpoint ? `${firstExposeTyped.label} ${endpoint}` : firstExposeTyped.label,
                            state_topic: firstExposeTyped.access & ACCESS_STATE,
                            value_template: `{{ value_json.${firstExposeTyped.property} }}`,
                            command_topic_prefix: endpoint,
                            command_topic: true,
                            command_topic_postfix: firstExposeTyped.property,
                            ...LIST_DISCOVERY_LOOKUP[firstExposeTyped.name],
                        },
                    });
                    break;
                }
                if (firstExposeTyped.access & ACCESS_STATE) {
                    discoveryEntries.push({
                        type: "sensor",
                        object_id: firstExposeTyped.property,
                        mockProperties: [{ property: firstExposeTyped.property, value: null }],
                        discovery_payload: {
                            name: endpoint ? `${firstExposeTyped.label} ${endpoint}` : firstExposeTyped.label,
                            // Truncate text if it's too long
                            // https://github.com/Koenkk/zigbee2mqtt/issues/23199
                            value_template: `{{ value_json.${firstExposeTyped.property} | default('',True) | string | truncate(254, True, '', 0) }}`,
                            ...LIST_DISCOVERY_LOOKUP[firstExposeTyped.name],
                        },
                    });
                }
                break;
            }
        }
        // Exposes with category 'config' or 'diagnostic' are always added to the respective category.
        // This takes precedence over definitions in this file.
        if (firstExpose.category === "config" || firstExpose.category === "diagnostic") {
            for (const entry of discoveryEntries) {
                entry.discovery_payload.entity_category = firstExpose.category;
            }
        }
        for (const entry of discoveryEntries) {
            // If a sensor has entity category `config`, then change
            // it to `diagnostic`. Sensors have no input, so can't be configured.
            // https://github.com/Koenkk/zigbee2mqtt/pull/19474
            if (["binary_sensor", "sensor"].includes(entry.type) && entry.discovery_payload.entity_category === "config") {
                entry.discovery_payload.entity_category = "diagnostic";
            }
            // Event entities cannot have an entity_category set.
            if (entry.type === "event" && entry.discovery_payload.entity_category) {
                delete entry.discovery_payload.entity_category;
            }
            // Let Home Assistant generate entity name when device_class is present
            if (entry.discovery_payload.device_class) {
                delete entry.discovery_payload.name;
            }
        }
        return discoveryEntries;
    }
    async onEntityRemoved(data) {
        logger_1.default.debug(`Clearing Home Assistant discovery for '${data.name}'`);
        const discovered = this.getDiscovered(data.entity.ID);
        for (const topic of Object.keys(discovered.messages)) {
            await this.mqtt.publish(topic, "", { clientOptions: { retain: true, qos: 1 }, baseTopic: this.discoveryTopic, skipReceive: false });
        }
        delete this.discovered[data.entity.ID];
    }
    async onGroupMembersChanged(data) {
        await this.discover(data.group);
    }
    async onPublishEntityState(data) {
        /**
         * In case we deal with a lightEndpoint configuration Zigbee2MQTT publishes
         * e.g. {state_l1: ON, brightness_l1: 250} to zigbee2mqtt/mydevice.
         * As the Home Assistant MQTT JSON light cannot be configured to use state_l1/brightness_l1
         * as the state variables, the state topic is set to zigbee2mqtt/mydevice/l1.
         * Here we retrieve all the attributes with the _l1 values and republish them on
         * zigbee2mqtt/mydevice/l1.
         */
        // biome-ignore lint/style/noNonNullAssertion: TODO: biome migration: should this be validated instead?
        const entity = this.zigbee.resolveEntity(data.entity.name);
        if (entity.isDevice()) {
            for (const topic in this.getDiscovered(entity).messages) {
                const topicMatch = topic.match(this.discoveryRegexWoTopic);
                /* v8 ignore start */
                if (!topicMatch) {
                    continue;
                }
                /* v8 ignore stop */
                const objectID = topicMatch[3];
                const lightMatch = /^light_(.*)/.exec(objectID);
                const coverMatch = /^cover_(.*)/.exec(objectID);
                const match = lightMatch || coverMatch;
                if (match) {
                    const endpoint = match[1];
                    const endpointRegExp = new RegExp(`(.*)_${endpoint}`);
                    const payload = {};
                    for (const key of Object.keys(data.message)) {
                        const keyMatch = endpointRegExp.exec(key);
                        if (keyMatch) {
                            payload[keyMatch[1]] = data.message[key];
                        }
                    }
                    await this.mqtt.publish(`${data.entity.name}/${endpoint}`, (0, json_stable_stringify_without_jsonify_1.default)(payload), {});
                }
            }
        }
        /**
         * Publish an empty value for click and action payload, in this way Home Assistant
         * can use Home Assistant entities in automations.
         * https://github.com/Koenkk/zigbee2mqtt/issues/959#issuecomment-480341347
         */
        if (this.legacyActionSensor && data.message.action) {
            await this.publishEntityState(data.entity, { action: "" });
        }
        /**
         * Implements the MQTT device trigger (https://www.home-assistant.io/integrations/device_trigger.mqtt/)
         * The MQTT device trigger does not support JSON parsing, so it cannot listen to zigbee2mqtt/my_device
         * Whenever a device publish an {action: *} we discover an MQTT device trigger sensor
         * and republish it to zigbee2mqtt/my_device/action
         */
        if (settings.get().advanced.output === "json" && entity.isDevice() && entity.definition && data.message.action) {
            const value = data.message.action.toString();
            await this.publishDeviceTriggerDiscover(entity, "action", value);
            await this.mqtt.publish(`${data.entity.name}/action`, value, {});
        }
    }
    async onEntityRenamed(data) {
        logger_1.default.debug(`Refreshing Home Assistant discovery topic for '${data.entity.name}'`);
        // Clear before rename so Home Assistant uses new friendly_name
        // https://github.com/Koenkk/zigbee2mqtt/issues/4096#issuecomment-674044916
        if (data.homeAssisantRename) {
            const discovered = this.getDiscovered(data.entity);
            for (const topic of Object.keys(discovered.messages)) {
                await this.mqtt.publish(topic, "", { clientOptions: { retain: true, qos: 1 }, baseTopic: this.discoveryTopic, skipReceive: false });
            }
            discovered.messages = {};
            // Make sure Home Assistant deletes the old entity first otherwise another one (_2) is created
            // https://github.com/Koenkk/zigbee2mqtt/issues/12610
            await utils_1.default.sleep(2);
        }
        await this.discover(data.entity);
        if (data.entity.isDevice()) {
            for (const config of this.getDiscovered(data.entity).triggers) {
                const key = config.substring(0, config.indexOf("_"));
                const value = config.substring(config.indexOf("_") + 1);
                await this.publishDeviceTriggerDiscover(data.entity, key, value, true);
            }
        }
    }
    getConfigs(entity) {
        const isDevice = entity.isDevice();
        const isGroup = entity.isGroup();
        /* v8 ignore next */
        if (!entity || (isDevice && !entity.definition))
            return [];
        let configs = [];
        if (isDevice) {
            const exposes = entity.exposes(); // avoid calling it hundred of times/s
            for (const expose of exposes) {
                configs.push(...this.exposeToConfig([expose], "device", exposes, entity.definition));
            }
        }
        else if (isGroup) {
            // group
            const exposesByType = {};
            const allExposes = [];
            for (const member of entity.zh.members) {
                const device = this.zigbee.resolveEntity(member.getDevice());
                if (device.definition) {
                    const exposes = device.exposes();
                    allExposes.push(...exposes);
                    for (const expose of exposes.filter((e) => GROUP_SUPPORTED_TYPES.includes(e.type))) {
                        let key = expose.type;
                        if (["switch", "lock", "cover"].includes(expose.type) && expose.endpoint) {
                            // A device can have multiple of these types which have to discovered separately.
                            // e.g. switch with property state and valve_detection.
                            const state = expose.features.find((f) => f.name === "state");
                            (0, node_assert_1.default)(state, `'switch', 'lock' or 'cover' is missing state`);
                            key += featurePropertyWithoutEndpoint(state);
                        }
                        if (!exposesByType[key])
                            exposesByType[key] = [];
                        exposesByType[key].push(expose);
                    }
                }
            }
            configs = [].concat(...Object.values(exposesByType).map((exposes) => this.exposeToConfig(exposes, "group", allExposes)));
        }
        else {
            // Discover bridge config.
            configs.push(...entity.configs);
        }
        if (isDevice && settings.get().advanced.last_seen !== "disable") {
            const config = {
                type: "sensor",
                object_id: "last_seen",
                mockProperties: [{ property: "last_seen", value: null }],
                discovery_payload: {
                    name: "Last seen",
                    value_template: "{{ value_json.last_seen }}",
                    icon: "mdi:clock",
                    enabled_by_default: false,
                    entity_category: "diagnostic",
                },
            };
            if (settings.get().advanced.last_seen.startsWith("ISO_8601")) {
                config.discovery_payload.device_class = "timestamp";
            }
            configs.push(config);
        }
        if (isDevice && entity.definition?.ota) {
            const updateSensor = {
                type: "update",
                object_id: "update",
                mockProperties: [{ property: "update", value: { state: null } }],
                discovery_payload: {
                    name: null,
                    entity_picture: "https://github.com/Koenkk/zigbee2mqtt/raw/master/images/logo.png",
                    state_topic: true,
                    device_class: "firmware",
                    entity_category: "config",
                    command_topic: `${settings.get().mqtt.base_topic}/bridge/request/device/ota_update/update`,
                    payload_install: `{"id": "${entity.ieeeAddr}"}`,
                    value_template: `{"latest_version":"{{ value_json['update']['latest_version'] }}","installed_version":"{{ value_json['update']['installed_version'] }}","update_percentage":{{ value_json['update'].get('progress', 'null') }},"in_progress":{{ (value_json['update']['state'] == 'updating')|lower }}}`,
                },
            };
            configs.push(updateSensor);
        }
        // Discover scenes.
        for (const endpointOrGroup of isDevice ? entity.zh.endpoints : isGroup ? [entity.zh] : []) {
            for (const scene of utils_1.default.getScenes(endpointOrGroup)) {
                const sceneEntry = {
                    type: "scene",
                    object_id: `scene_${scene.id}`,
                    mockProperties: [],
                    discovery_payload: {
                        name: `${scene.name}`,
                        state_topic: false,
                        command_topic: true,
                        payload_on: `{ "scene_recall": ${scene.id} }`,
                        object_id_postfix: `_${scene.name.replace(/\s+/g, "_").toLowerCase()}`,
                    },
                };
                configs.push(sceneEntry);
            }
        }
        // deep clone of the config objects
        configs = JSON.parse(JSON.stringify(configs));
        if (entity.options.homeassistant) {
            const s = entity.options.homeassistant;
            configs = configs.filter((config) => s[config.object_id] === undefined || s[config.object_id] != null);
            for (const config of configs) {
                const configOverride = s[config.object_id];
                if (configOverride) {
                    config.object_id = configOverride.object_id || config.object_id;
                    config.type = configOverride.type || config.type;
                }
            }
        }
        return configs;
    }
    async discover(entity, publish = true) {
        // Handle type differences.
        const isDevice = entity.isDevice();
        const isGroup = entity.isGroup();
        if (isGroup && entity.zh.members.length === 0) {
            return;
        }
        if (isDevice &&
            (!entity.definition || !entity.interviewed || (entity.options.homeassistant !== undefined && !entity.options.homeassistant))) {
            return;
        }
        const discovered = this.getDiscovered(entity);
        discovered.discovered = true;
        const lastDiscoveredTopics = Object.keys(discovered.messages);
        const newDiscoveredTopics = new Set();
        for (const config of this.getConfigs(entity)) {
            const payload = { ...config.discovery_payload };
            const baseTopic = `${settings.get().mqtt.base_topic}/${entity.name}`;
            let stateTopic = baseTopic;
            if (payload.state_topic_postfix) {
                stateTopic += `/${payload.state_topic_postfix}`;
                delete payload.state_topic_postfix;
            }
            if (payload.state_topic === undefined || payload.state_topic) {
                payload.state_topic = stateTopic;
            }
            else {
                if (payload.state_topic !== undefined) {
                    delete payload.state_topic;
                }
            }
            if (payload.position_topic) {
                payload.position_topic = stateTopic;
            }
            if (payload.tilt_status_topic) {
                payload.tilt_status_topic = stateTopic;
            }
            const devicePayload = this.getDevicePayload(entity);
            // Suggest object_id (entity_id) for entity
            payload.object_id = devicePayload.name.replace(/\s+/g, "_").toLowerCase();
            if (config.object_id.startsWith(config.type) && config.object_id.includes("_")) {
                payload.object_id += `_${config.object_id.split(/_(.+)/)[1]}`;
            }
            else if (!config.object_id.startsWith(config.type)) {
                payload.object_id += `_${config.object_id}`;
            }
            // Allow customization of the `payload.object_id` without touching the other uses of `config.object_id`
            // (e.g. for setting the `payload.unique_id` and as an internal key).
            payload.object_id = `${payload.object_id}${payload.object_id_postfix ?? ""}`;
            delete payload.object_id_postfix;
            // Set `default_entity_id`, as of HA 2025.10 this replaces the `object_id`.
            // For migration purposes we set both for now.
            // https://github.com/home-assistant/core/pull/151775
            payload.default_entity_id = `${config.type}.${payload.object_id}`;
            // Set unique_id
            payload.unique_id = `${entity.options.ID}_${config.object_id}_${settings.get().mqtt.base_topic}`;
            // Attributes for device registry and origin
            payload.device = devicePayload;
            payload.origin = this.discoveryOrigin;
            // Availability payload (can be disabled by setting `payload.availability = false`).
            if (payload.availability === undefined || payload.availability) {
                payload.availability = [{ topic: `${settings.get().mqtt.base_topic}/bridge/state` }];
                if (isDevice || isGroup) {
                    if (utils_1.default.isAvailabilityEnabledForEntity(entity, settings.get())) {
                        payload.availability_mode = "all";
                        payload.availability.push({ topic: `${baseTopic}/availability` });
                    }
                }
                else {
                    // Bridge availability is different.
                    payload.availability_mode = "all";
                }
                if (isDevice && entity.options.disabled) {
                    // Mark disabled device always as unavailable
                    for (const entry of payload.availability) {
                        entry.value_template = '{{ "offline" }}';
                    }
                }
                else {
                    for (const entry of payload.availability) {
                        entry.value_template = "{{ value_json.state }}";
                    }
                }
            }
            else {
                delete payload.availability;
            }
            const commandTopicPrefix = payload.command_topic_prefix ? `${payload.command_topic_prefix}/` : "";
            delete payload.command_topic_prefix;
            const commandTopicPostfix = payload.command_topic_postfix ? `/${payload.command_topic_postfix}` : "";
            delete payload.command_topic_postfix;
            const commandTopic = `${baseTopic}/${commandTopicPrefix}set${commandTopicPostfix}`;
            if (payload.command_topic && typeof payload.command_topic !== "string") {
                payload.command_topic = commandTopic;
            }
            if (payload.set_position_topic) {
                payload.set_position_topic = commandTopic;
            }
            if (payload.tilt_command_topic) {
                payload.tilt_command_topic = `${baseTopic}/${commandTopicPrefix}set/tilt`;
            }
            if (payload.mode_state_topic) {
                payload.mode_state_topic = stateTopic;
            }
            if (payload.mode_command_topic) {
                payload.mode_command_topic = `${baseTopic}/${commandTopicPrefix}set/system_mode`;
            }
            if (payload.current_temperature_topic) {
                payload.current_temperature_topic = stateTopic;
            }
            if (payload.temperature_state_topic) {
                payload.temperature_state_topic = stateTopic;
            }
            if (payload.temperature_low_state_topic) {
                payload.temperature_low_state_topic = stateTopic;
            }
            if (payload.temperature_high_state_topic) {
                payload.temperature_high_state_topic = stateTopic;
            }
            if (payload.temperature_command_topic) {
                payload.temperature_command_topic = `${baseTopic}/${commandTopicPrefix}set/${payload.temperature_command_topic}`;
            }
            if (payload.temperature_low_command_topic) {
                payload.temperature_low_command_topic = `${baseTopic}/${commandTopicPrefix}set/${payload.temperature_low_command_topic}`;
            }
            if (payload.temperature_high_command_topic) {
                payload.temperature_high_command_topic = `${baseTopic}/${commandTopicPrefix}set/${payload.temperature_high_command_topic}`;
            }
            if (payload.fan_mode_state_topic) {
                payload.fan_mode_state_topic = stateTopic;
            }
            if (payload.fan_mode_command_topic) {
                payload.fan_mode_command_topic = `${baseTopic}/${commandTopicPrefix}set/fan_mode`;
            }
            if (payload.swing_mode_state_topic) {
                payload.swing_mode_state_topic = stateTopic;
            }
            if (payload.swing_mode_command_topic) {
                payload.swing_mode_command_topic = `${baseTopic}/${commandTopicPrefix}set/swing_mode`;
            }
            if (payload.percentage_state_topic) {
                payload.percentage_state_topic = stateTopic;
            }
            if (payload.percentage_command_topic) {
                payload.percentage_command_topic = `${baseTopic}/${commandTopicPrefix}set/${payload.percentage_command_topic}`;
            }
            if (payload.preset_mode_state_topic) {
                payload.preset_mode_state_topic = stateTopic;
            }
            if (payload.preset_mode_command_topic) {
                payload.preset_mode_command_topic = `${baseTopic}/${commandTopicPrefix}set/${payload.preset_mode_command_topic}`;
            }
            if (payload.action_topic) {
                payload.action_topic = stateTopic;
            }
            if (payload.current_humidity_topic) {
                payload.current_humidity_topic = stateTopic;
            }
            // Override configuration with user settings.
            if (entity.options.homeassistant != null) {
                const add = (obj, ignoreName) => {
                    for (const key in obj) {
                        if (key === "type" || key === "object_id") {
                            continue;
                        }
                        if (ignoreName && key === "name") {
                            continue;
                        }
                        if (["number", "string", "boolean"].includes(typeof obj[key]) || Array.isArray(obj[key])) {
                            payload[key] = obj[key];
                        }
                        else if (obj[key] === null) {
                            delete payload[key];
                        }
                        else if (key === "device" && typeof obj[key] === "object") {
                            for (const devKey in obj.device) {
                                payload.device[devKey] = obj.device[devKey];
                            }
                        }
                    }
                };
                add(entity.options.homeassistant, true);
                if (entity.options.homeassistant[config.object_id] != null) {
                    add(entity.options.homeassistant[config.object_id], false);
                }
            }
            if (entity.isDevice()) {
                try {
                    entity.definition?.meta?.overrideHaDiscoveryPayload?.(payload);
                }
                catch (error) {
                    logger_1.default.error(`Failed to override HA discovery payload (${error.stack})`);
                }
            }
            const topic = this.getDiscoveryTopic(config, entity);
            const payloadStr = (0, json_stable_stringify_without_jsonify_1.default)(payload);
            newDiscoveredTopics.add(topic);
            // Only discover when not discovered yet
            const discoveredMessage = discovered.messages[topic];
            if (!discoveredMessage || discoveredMessage.payload !== payloadStr || !discoveredMessage.published) {
                discovered.messages[topic] = { payload: payloadStr, published: publish };
                if (publish) {
                    await this.mqtt.publish(topic, payloadStr, {
                        clientOptions: { retain: true, qos: 1 },
                        baseTopic: this.discoveryTopic,
                        skipReceive: false,
                    });
                }
            }
            else {
                logger_1.default.debug(`Skipping discovery of '${topic}', already discovered`);
            }
            if (config.mockProperties) {
                for (const mockProperty of config.mockProperties) {
                    discovered.mockProperties.add(mockProperty);
                }
            }
        }
        for (const topic of lastDiscoveredTopics) {
            const isDeviceAutomation = topic.match(this.discoveryRegexWoTopic)?.[1] === "device_automation";
            if (!newDiscoveredTopics.has(topic) && !isDeviceAutomation) {
                await this.mqtt.publish(topic, "", { clientOptions: { retain: true, qos: 1 }, baseTopic: this.discoveryTopic, skipReceive: false });
            }
        }
    }
    async onMQTTMessage(data) {
        const discoveryMatch = data.topic.match(this.discoveryRegex);
        const isDeviceAutomation = discoveryMatch && discoveryMatch[1] === "device_automation";
        if (discoveryMatch) {
            // Clear outdated discovery configs and remember already discovered device_automations
            let message;
            try {
                message = JSON.parse(data.message);
                const baseTopic = `${settings.get().mqtt.base_topic}/`;
                if (isDeviceAutomation && (!message.topic || !message.topic.startsWith(baseTopic))) {
                    return;
                }
                if (!isDeviceAutomation && (!message.availability || !message.availability[0].topic.startsWith(baseTopic))) {
                    return;
                }
            }
            catch {
                return;
            }
            // Group discovery topic uses "ENCODEDBASETOPIC_GROUPID", device use ieeeAddr
            const ID = discoveryMatch[2].includes("_") ? discoveryMatch[2].split("_")[1] : discoveryMatch[2];
            const entity = ID === this.bridge.ID ? this.bridge : this.zigbee.resolveEntity(ID);
            let clear = !entity || (entity.isDevice() && !entity.definition);
            // Only save when topic matches otherwise config is not updated when renamed by editing configuration.yaml
            if (entity) {
                const key = `${discoveryMatch[3].substring(0, discoveryMatch[3].indexOf("_"))}`;
                const triggerTopic = `${settings.get().mqtt.base_topic}/${entity.name}/${key}`;
                if (isDeviceAutomation && message.topic === triggerTopic) {
                    this.getDiscovered(ID).triggers.add(discoveryMatch[3]);
                }
            }
            const topic = data.topic.substring(this.discoveryTopic.length + 1);
            if (!clear && !isDeviceAutomation && entity && !(topic in this.getDiscovered(entity).messages)) {
                clear = true;
            }
            // Device was flagged to be excluded from homeassistant discovery
            clear = clear || Boolean(entity && entity.options.homeassistant !== undefined && !entity.options.homeassistant);
            if (clear) {
                logger_1.default.debug(`Clearing outdated Home Assistant config '${data.topic}'`);
                await this.mqtt.publish(topic, "", { clientOptions: { retain: true, qos: 1 }, baseTopic: this.discoveryTopic, skipReceive: false });
            }
            else if (entity) {
                this.getDiscovered(entity).messages[topic] = { payload: (0, json_stable_stringify_without_jsonify_1.default)(message), published: true };
            }
        }
        else if (data.topic === this.statusTopic && data.message.toLowerCase() === "online") {
            const timer = setTimeout(async () => {
                // Publish all device states.
                for (const entity of this.zigbee.devicesAndGroupsIterator(utils_1.default.deviceNotCoordinator)) {
                    if (this.state.exists(entity)) {
                        await this.publishEntityState(entity, this.state.get(entity), "publishCached");
                    }
                }
                clearTimeout(timer);
            }, 30000);
        }
    }
    async onZigbeeEvent(data) {
        if (!this.getDiscovered(data.device).discovered) {
            await this.discover(data.device);
        }
    }
    async onScenesChanged(data) {
        // Re-trigger MQTT discovery of changed devices and groups, similar to bridge.ts
        // First, clear existing scene discovery topics
        logger_1.default.debug(`Clearing Home Assistant scene discovery for '${data.entity.name}'`);
        const discovered = this.getDiscovered(data.entity);
        for (const topic of Object.keys(discovered.messages)) {
            if (topic.startsWith("scene")) {
                await this.mqtt.publish(topic, "", { clientOptions: { retain: true, qos: 1 }, baseTopic: this.discoveryTopic, skipReceive: false });
                delete discovered.messages[topic];
            }
        }
        // Make sure Home Assistant deletes the old entity first otherwise another one (_2) is created
        // https://github.com/Koenkk/zigbee2mqtt/issues/12610
        logger_1.default.debug("Finished clearing scene discovery topics, waiting for Home Assistant.");
        await utils_1.default.sleep(2);
        // Re-discover entity (including any new scenes).
        logger_1.default.debug("Re-discovering entities with their scenes.");
        await this.discover(data.entity);
    }
    getDevicePayload(entity) {
        const identifierPostfix = entity.isGroup() ? `zigbee2mqtt_${this.getEncodedBaseTopic()}` : "zigbee2mqtt";
        // Allow device name to be overridden by homeassistant config
        let deviceName = entity.name;
        if (typeof entity.options.homeassistant?.name === "string") {
            deviceName = entity.options.homeassistant.name;
        }
        const payload = {
            identifiers: [`${identifierPostfix}_${entity.options.ID}`],
            name: deviceName,
            sw_version: `Zigbee2MQTT ${this.zigbee2MQTTVersion}`,
        };
        const url = settings.get().frontend?.url ?? "";
        // Since zigbee2mqtt-windfront support multiple instances the configuration URL contains the
        // instance ID. Since we don't know which instance it is we always point to 0.
        // https://github.com/Koenkk/zigbee2mqtt/issues/28936
        const urlEntityPostfix = settings.get().frontend.package === "zigbee2mqtt-windfront" ? "0/" : "";
        if (entity.isDevice()) {
            (0, node_assert_1.default)(entity.definition, `Cannot 'getDevicePayload' for unsupported device`);
            payload.model = entity.definition.description;
            payload.model_id = entity.definition.model;
            payload.manufacturer = entity.definition.vendor;
            payload.sw_version = entity.zh.softwareBuildID;
            payload.hw_version = entity.zh.hardwareVersion;
            payload.configuration_url = `${url}/#/device/${urlEntityPostfix}${entity.ieeeAddr}/info`;
        }
        else if (entity.isGroup()) {
            payload.model = "Group";
            payload.manufacturer = "Zigbee2MQTT";
            payload.configuration_url = `${url}/#/group/${urlEntityPostfix}${entity.ID}`;
        }
        else {
            payload.model = "Bridge";
            payload.manufacturer = "Zigbee2MQTT";
            payload.hw_version = `${entity.hardwareVersion} ${entity.firmwareVersion}`;
            payload.sw_version = this.zigbee2MQTTVersion;
            payload.configuration_url = `${url}/#/settings`;
        }
        if (!url) {
            delete payload.configuration_url;
        }
        // Link devices & groups to bridge.
        if (entity !== this.bridge) {
            payload.via_device = this.bridgeIdentifier;
        }
        return payload;
    }
    adjustMessageBeforePublish(entity, message) {
        for (const mockProperty of this.getDiscovered(entity).mockProperties) {
            if (message[mockProperty.property] === undefined) {
                message[mockProperty.property] = mockProperty.value;
            }
        }
        // Copy hue -> h, saturation -> s to make homeassistant happy
        if (message.color !== undefined) {
            if (message.color.hue !== undefined) {
                message.color.h = message.color.hue;
            }
            if (message.color.saturation !== undefined) {
                message.color.s = message.color.saturation;
            }
        }
        if (entity.isDevice() && entity.definition?.ota && message.update?.latest_version == null) {
            message.update = { ...message.update, installed_version: -1, latest_version: -1 };
        }
    }
    getEncodedBaseTopic() {
        return settings
            .get()
            .mqtt.base_topic.split("")
            .map((s) => s.charCodeAt(0).toString())
            .join("");
    }
    getDiscoveryTopic(config, entity) {
        const key = entity.isDevice() ? entity.ieeeAddr : `${this.getEncodedBaseTopic()}_${entity.ID}`;
        return `${config.type}/${key}/${config.object_id}/config`;
    }
    async publishDeviceTriggerDiscover(device, key, value, force = false) {
        const haConfig = device.options.homeassistant;
        if (device.options.homeassistant !== undefined &&
            (haConfig == null || (haConfig.device_automation !== undefined && typeof haConfig === "object" && haConfig.device_automation == null))) {
            return;
        }
        const discovered = this.getDiscovered(device);
        const discoveredKey = `${key}_${value}`;
        if (discovered.triggers.has(discoveredKey) && !force) {
            return;
        }
        const config = {
            type: "device_automation",
            object_id: `${key}_${value}`,
            mockProperties: [],
            discovery_payload: {
                automation_type: "trigger",
                type: key,
            },
        };
        const topic = this.getDiscoveryTopic(config, device);
        const payload = {
            ...config.discovery_payload,
            subtype: value,
            payload: value,
            topic: `${settings.get().mqtt.base_topic}/${device.name}/${key}`,
            device: this.getDevicePayload(device),
            origin: this.discoveryOrigin,
        };
        await this.mqtt.publish(topic, (0, json_stable_stringify_without_jsonify_1.default)(payload), {
            clientOptions: { retain: true, qos: 1 },
            baseTopic: this.discoveryTopic,
            skipReceive: false,
        });
        discovered.triggers.add(discoveredKey);
    }
    getBridgeEntity(coordinatorVersion) {
        const coordinatorIeeeAddress = this.zigbee.firstCoordinatorEndpoint().deviceIeeeAddress;
        const discovery = [];
        const bridge = new Bridge(coordinatorIeeeAddress, coordinatorVersion, discovery);
        const baseTopic = `${settings.get().mqtt.base_topic}/${bridge.name}`;
        discovery.push(
        // Binary sensors.
        {
            type: "binary_sensor",
            object_id: "connection_state",
            mockProperties: [],
            discovery_payload: {
                name: "Connection state",
                device_class: "connectivity",
                entity_category: "diagnostic",
                state_topic: true,
                state_topic_postfix: "state",
                value_template: "{{ value_json.state }}",
                payload_on: "online",
                payload_off: "offline",
                availability: false,
            },
        }, {
            type: "binary_sensor",
            object_id: "restart_required",
            mockProperties: [],
            discovery_payload: {
                name: "Restart required",
                device_class: "problem",
                entity_category: "diagnostic",
                enabled_by_default: false,
                state_topic: true,
                state_topic_postfix: "info",
                value_template: "{{ value_json.restart_required }}",
                payload_on: true,
                payload_off: false,
            },
        }, 
        // Buttons.
        {
            type: "button",
            object_id: "restart",
            mockProperties: [],
            discovery_payload: {
                name: "Restart",
                device_class: "restart",
                state_topic: false,
                command_topic: `${baseTopic}/request/restart`,
                payload_press: "",
            },
        }, 
        // Selects.
        {
            type: "select",
            object_id: "log_level",
            mockProperties: [],
            discovery_payload: {
                name: "Log level",
                entity_category: "config",
                state_topic: true,
                state_topic_postfix: "info",
                value_template: "{{ value_json.log_level | lower }}",
                command_topic: `${baseTopic}/request/options`,
                command_template: '{"options": {"advanced": {"log_level": "{{ value }}" } } }',
                options: settings.LOG_LEVELS,
            },
        }, 
        // Sensors:
        {
            type: "sensor",
            object_id: "version",
            mockProperties: [],
            discovery_payload: {
                name: "Version",
                icon: "mdi:zigbee",
                entity_category: "diagnostic",
                state_topic: true,
                state_topic_postfix: "info",
                value_template: "{{ value_json.version }}",
            },
        }, {
            type: "sensor",
            object_id: "coordinator_version",
            mockProperties: [],
            discovery_payload: {
                name: "Coordinator version",
                icon: "mdi:chip",
                entity_category: "diagnostic",
                enabled_by_default: false,
                state_topic: true,
                state_topic_postfix: "info",
                value_template: "{{ value_json.coordinator.meta.revision }}",
            },
        }, {
            type: "sensor",
            object_id: "network_map",
            mockProperties: [],
            discovery_payload: {
                name: "Network map",
                entity_category: "diagnostic",
                enabled_by_default: false,
                state_topic: true,
                state_topic_postfix: "response/networkmap",
                value_template: "{{ now().strftime('%Y-%m-%d %H:%M:%S') }}",
                json_attributes_topic: `${baseTopic}/response/networkmap`,
                json_attributes_template: "{{ value_json.data.value | tojson }}",
            },
        }, 
        // Switches.
        {
            type: "switch",
            object_id: "permit_join",
            mockProperties: [],
            discovery_payload: {
                name: "Permit join",
                icon: "mdi:human-greeting-proximity",
                state_topic: true,
                state_topic_postfix: "info",
                value_template: "{{ value_json.permit_join | lower }}",
                command_topic: `${baseTopic}/request/permit_join`,
                state_on: "true",
                state_off: "false",
                payload_on: '{"time": 254}',
                payload_off: '{"time": 0}',
            },
        });
        return bridge;
    }
    parseActionValue(action) {
        // Handle standard actions.
        for (const p of ACTION_PATTERNS) {
            const m = action.match(p);
            if (m?.groups?.action) {
                return this.buildAction(m.groups);
            }
        }
        // Handle wildcard actions.
        let m = action.match(/^(?<action>recall|scene)_\*(?:_(?<endpoint>e1|e2|s1|s2))?$/);
        if (m?.groups?.action) {
            logger_1.default.debug(`Found scene wildcard action ${m.groups.action}`);
            return this.buildAction(m.groups, { scene: "wildcard" });
        }
        m = action.match(/^(?<actionPrefix>region_)\*_(?<action>enter|leave|occupied|unoccupied)$/);
        if (m?.groups?.action) {
            logger_1.default.debug(`Found region wildcard action ${m.groups.action}`);
            return this.buildAction(m.groups, { region: "wildcard" });
        }
        // If nothing matches, keep the plain action value.
        return { action };
    }
    buildAction(groups, props = {}) {
        utils_1.default.removeNullPropertiesFromObject(groups);
        let a = groups.action;
        if (groups?.actionPrefix) {
            a = groups.actionPrefix + a;
            delete groups.actionPrefix;
        }
        return { ...groups, action: a, ...props };
    }
    prepareActionEventTypes(values) {
        return utils_1.default.arrayUnique(values.map((v) => this.parseActionValue(v.toString()).action).filter((v) => !v.includes("*")));
    }
    parseGroupsFromRegex(pattern) {
        return [...pattern.matchAll(/\(\?<([a-zA-Z]+)>/g)].map((v) => v[1]);
    }
    getActionValueTemplate() {
        // TODO: Implement parsing for all event types.
        const patterns = ACTION_PATTERNS.map((v) => {
            return `{"pattern": '${v.replaceAll(/\?<([a-zA-Z]+)>/g, "?P<$1>")}', "groups": [${this.parseGroupsFromRegex(v)
                .map((g) => `"${g}"`)
                .join(", ")}]}`;
        }).join(",\n");
        const value_template = `{% set patterns = [\n${patterns}\n] %}
{% set action_value = value_json.action|default('') %}
{% set ns = namespace(r=[('action', action_value)]) %}
{% for p in patterns %}
  {% set m = action_value|regex_findall(p.pattern) %}
  {% if m[0] is undefined %}{% continue %}{% endif %}
  {% for key, value in zip(p.groups, m[0]) %}
    {% set ns.r = ns.r|rejectattr(0, 'eq', key)|list + [(key, value)] %}
  {% endfor %}
{% endfor %}
{% if (ns.r|selectattr(0, 'eq', 'actionPrefix')|first) is defined %}
  {% set ns.r = ns.r|rejectattr(0, 'eq', 'action')|list + [('action', ns.r|selectattr(0, 'eq', 'actionPrefix')|map(attribute=1)|first + ns.r|selectattr(0, 'eq', 'action')|map(attribute=1)|first)] %}
{% endif %}
{% set ns.r = ns.r + [('event_type', ns.r|selectattr(0, 'eq', 'action')|map(attribute=1)|first)] %}
{{dict.from_keys(ns.r|rejectattr(0, 'in', ('action', 'actionPrefix'))|reject('eq', ('event_type', None))|reject('eq', ('event_type', '')))|to_json}}`;
        return value_template;
    }
}
exports.HomeAssistant = HomeAssistant;
__decorate([
    bind_decorator_1.default
], HomeAssistant.prototype, "onEntityRemoved", null);
__decorate([
    bind_decorator_1.default
], HomeAssistant.prototype, "onGroupMembersChanged", null);
__decorate([
    bind_decorator_1.default
], HomeAssistant.prototype, "onPublishEntityState", null);
__decorate([
    bind_decorator_1.default
], HomeAssistant.prototype, "onEntityRenamed", null);
__decorate([
    bind_decorator_1.default
], HomeAssistant.prototype, "onMQTTMessage", null);
__decorate([
    bind_decorator_1.default
], HomeAssistant.prototype, "onZigbeeEvent", null);
__decorate([
    bind_decorator_1.default
], HomeAssistant.prototype, "onScenesChanged", null);
exports.default = HomeAssistant;
//# sourceMappingURL=data:application/json;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoiaG9tZWFzc2lzdGFudC5qcyIsInNvdXJjZVJvb3QiOiIiLCJzb3VyY2VzIjpbIi4uLy4uL2xpYi9leHRlbnNpb24vaG9tZWFzc2lzdGFudC50cyJdLCJuYW1lcyI6W10sIm1hcHBpbmdzIjoiOzs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7QUFBQSw4REFBaUM7QUFDakMsb0VBQWtDO0FBQ2xDLGtIQUE4RDtBQUc5RCw0REFBb0M7QUFDcEMsMkRBQTZDO0FBQzdDLHVEQUE4STtBQUM5SSw0REFBb0M7QUE0QnBDLE1BQU0sZUFBZSxHQUFhO0lBQzlCLDJFQUEyRTtJQUMzRSxxREFBcUQ7SUFDckQsMEZBQTBGO0lBQzFGLDRFQUE0RTtJQUM1RSx5REFBeUQ7Q0FDNUQsQ0FBQztBQUNGLE1BQU0sWUFBWSxHQUFHLEtBQUssQ0FBQztBQUMzQixNQUFNLFVBQVUsR0FBRyxLQUFLLENBQUM7QUFDekIsTUFBTSxxQkFBcUIsR0FBMEIsQ0FBQyxPQUFPLEVBQUUsUUFBUSxFQUFFLE1BQU0sRUFBRSxPQUFPLENBQUMsQ0FBQztBQUMxRixNQUFNLG9CQUFvQixHQUEwQixDQUFDLFNBQVMsRUFBRSxNQUFNLEVBQUUsU0FBUyxFQUFFLElBQUksRUFBRSxRQUFRLENBQUMsQ0FBQztBQUNuRyxNQUFNLG9CQUFvQixHQUEwQixDQUFDLFNBQVMsRUFBRSxPQUFPLEVBQUUsVUFBVSxFQUFFLE1BQU0sRUFBRSxTQUFTLEVBQUUsTUFBTSxFQUFFLFdBQVcsQ0FBQyxDQUFDO0FBQzdILE1BQU0sb0JBQW9CLEdBQTBCLENBQUMsU0FBUyxFQUFFLE1BQU0sRUFBRSxPQUFPLEVBQUUsUUFBUSxDQUFDLENBQUM7QUFDM0YsTUFBTSxnQkFBZ0IsR0FBMEIsQ0FBQyxpQkFBaUIsRUFBRSxrQkFBa0IsRUFBRSxXQUFXLEVBQUUsV0FBVyxDQUFDLENBQUM7QUFDbEgsTUFBTSx1QkFBdUIsR0FBNEI7SUFDckQsc0JBQXNCLEVBQUUsRUFBQyxJQUFJLEVBQUUsWUFBWSxF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