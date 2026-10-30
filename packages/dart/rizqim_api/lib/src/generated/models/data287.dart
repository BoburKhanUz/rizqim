// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'slots5.dart';

part 'data287.g.dart';

@JsonSerializable()
class Data287 {
  const Data287({
    required this.enabled,
    required this.minLeadMinutes,
    required this.slotMinutes,
    required this.slots,
  });

  factory Data287.fromJson(Map<String, Object?> json) =>
      _$Data287FromJson(json);

  final bool enabled;
  @JsonKey(name: 'min_lead_minutes')
  final int minLeadMinutes;
  @JsonKey(name: 'slot_minutes')
  final int slotMinutes;
  final List<Slots5> slots;

  Map<String, Object?> toJson() => _$Data287ToJson(this);
}
