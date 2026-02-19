"""
Example plugins for DomoHub
"""

from .philips_hue import PhilipsHuePlugin
from .xiaomi_sensors import XiaomiSensorsPlugin

__all__ = ["PhilipsHuePlugin", "XiaomiSensorsPlugin"]
