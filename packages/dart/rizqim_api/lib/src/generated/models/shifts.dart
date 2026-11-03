// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'shifts.g.dart';

@JsonSerializable()
class Shifts {
  const Shifts({
    required this.uuid,
    required this.businessDate,
    required this.zNumber,
    required this.openedAt,
    required this.closedAt,
    required this.status,
  });

  factory Shifts.fromJson(Map<String, Object?> json) => _$ShiftsFromJson(json);

  final String uuid;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'z_number')
  final int? zNumber;
  @JsonKey(name: 'opened_at')
  final String? openedAt;
  @JsonKey(name: 'closed_at')
  final String? closedAt;
  final String status;

  Map<String, Object?> toJson() => _$ShiftsToJson(this);
}
