// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// What a kitchen station is (kitchen_stations.kind): the choyxona's osh/qozon, mangal (grill), salat va bar, choy, or an ordinary kitchen. Clients pick the KDS layout from it (the mangal station counts skewers). P6-33 (KDS v2, docs/POS_SPEC.md "KDS v2"): the universal stations of a fast food or a coffee shop — `grill`, `fryer` (fritur), `drinks`, `barista` — and `expo`, the assembly screen: an order cooked on several stations is put together there and its bump makes the number ready (module `kds_expo`). An expo station gets no dishes of its own (its routes are refused); its time target is the assembly time.
///
@JsonEnum()
enum KitchenStationKind {
  @JsonValue('osh')
  osh('osh'),
  @JsonValue('mangal')
  mangal('mangal'),
  @JsonValue('salad_bar')
  saladBar('salad_bar'),
  @JsonValue('tea')
  tea('tea'),
  @JsonValue('kitchen')
  kitchen('kitchen'),
  @JsonValue('other')
  other('other'),
  @JsonValue('grill')
  grill('grill'),
  @JsonValue('fryer')
  fryer('fryer'),
  @JsonValue('drinks')
  drinks('drinks'),
  @JsonValue('barista')
  barista('barista'),
  @JsonValue('expo')
  expo('expo'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const KitchenStationKind(this.json);

  factory KitchenStationKind.fromJson(String json) =>
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
  static List<KitchenStationKind> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
