// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'pickup29.g.dart';

@JsonSerializable()
class Pickup29 {
  const Pickup29({
    required this.pickupAt,
    required this.kitchenFireAt,
    required this.kitchenFiredAt,
    required this.waiting,
    required this.localTime,
  });

  factory Pickup29.fromJson(Map<String, Object?> json) =>
      _$Pickup29FromJson(json);

  @JsonKey(name: 'pickup_at')
  final String pickupAt;
  @JsonKey(name: 'kitchen_fire_at')
  final String? kitchenFireAt;
  @JsonKey(name: 'kitchen_fired_at')
  final String? kitchenFiredAt;
  final bool waiting;
  @JsonKey(name: 'local_time')
  final String localTime;

  Map<String, Object?> toJson() => _$Pickup29ToJson(this);
}
