// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'pickup.g.dart';

@JsonSerializable()
class Pickup {
  const Pickup({
    required this.minLeadMinutes,
    required this.slotMinutes,
    required this.maxDaysAhead,
    required this.prepMinutes,
  });

  factory Pickup.fromJson(Map<String, Object?> json) => _$PickupFromJson(json);

  @JsonKey(name: 'min_lead_minutes')
  final int minLeadMinutes;
  @JsonKey(name: 'slot_minutes')
  final int slotMinutes;
  @JsonKey(name: 'max_days_ahead')
  final int maxDaysAhead;
  @JsonKey(name: 'prep_minutes')
  final int prepMinutes;

  Map<String, Object?> toJson() => _$PickupToJson(this);
}
