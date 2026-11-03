// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P6-32 (B2): where a modifier group's options are shown on the cashier's tile (modifier_groups.display_mode). A hint for the till and the waiter app: the server sells the same options whichever way they were picked. - dialog: the modifier window, as before (the default);  - inline: the options as chips beside the menu tile, no window;  - size: a one-tap size selector ("Kichik / O'rta / Katta"); the group takes exactly one option (max_select 1).
///
@JsonEnum()
enum ModifierDisplayMode {
  @JsonValue('dialog')
  dialog('dialog'),
  @JsonValue('inline')
  inline('inline'),
  @JsonValue('size')
  size('size'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const ModifierDisplayMode(this.json);

  factory ModifierDisplayMode.fromJson(String json) =>
      values.firstWhere((e) => e.json == json, orElse: () => $unknown);

  final String? json;
  String toJson() {
    final value = json;
    if (value == null) {
      throw StateError(
        'Cannot convert enum value with null JSON representation to String. '
        'This usually happens for \$unknown or @JsonValue(null) entries.',
      );
    }
    return value as String;
  }

  @override
  String toString() => json?.toString() ?? super.toString();

  /// Returns all defined enum values excluding the $unknown value.
  static List<ModifierDisplayMode> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
