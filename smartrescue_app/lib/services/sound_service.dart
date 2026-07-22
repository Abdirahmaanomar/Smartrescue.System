import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

class SoundService {
  static const String _soundKey = 'sound_alerts_enabled';
  static final AudioPlayer _beepPlayer = AudioPlayer();
  static final AudioPlayer _sirenPlayer = AudioPlayer();

  // ─── Preferences ─────────────────────────────────────────────────────────────
  static Future<bool> isSoundEnabled() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getBool(_soundKey) ?? true;
    } catch (e) {
      debugPrint('[SoundService] Error checking sound preference: $e');
      return true;
    }
  }

  static Future<void> setSoundEnabled(bool enabled) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool(_soundKey, enabled);
      if (!enabled) {
        stopAmbulanceSirenLoop();
      }
    } catch (e) {
      debugPrint('[SoundService] Error saving sound preference: $e');
    }
  }

  // ─── Notification Beep ───────────────────────────────────────────────────────
  /// Plays three short tones [880Hz, 1100Hz, 880Hz] — identical to the web dashboard.
  static Future<void> playBeep() async {
    if (!await isSoundEnabled()) return;
    try {
      await _beepPlayer.stop();
      await _beepPlayer.play(AssetSource('sounds/beep.wav'));
    } catch (e, stack) {
      debugPrint('[SoundService] Error playing beep: $e\n$stack');
    }
  }

  static Future<void> playSosSiren() => playBeep();
  static Future<void> playNotificationBeep() => playBeep();

  // ─── Ambulance Siren Loop ────────────────────────────────────────────────────
  /// Plays a looping ambulance wail sweep [600Hz ↔ 1100Hz sawtooth sweep].
  /// Disabled to only keep the single web beep.
  static Future<void> playAmbulanceSirenLoop() async {
    return;
  }

  // ─── Stop Siren ──────────────────────────────────────────────────────────────
  static Future<void> stopAmbulanceSirenLoop() async {
    try {
      await _sirenPlayer.stop();
    } catch (e) {
      debugPrint('[SoundService] Error stopping siren loop: $e');
    }
  }
}
