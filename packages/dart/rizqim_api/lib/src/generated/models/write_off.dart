// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'write_off.g.dart';

@JsonSerializable()
class WriteOff {
  const WriteOff({
    required this.uuid,
    required this.quantity,
    required this.unit,
    required this.reason,
    required this.note,
    required this.businessDate,
    required this.shiftId,
    required this.approvedBy,
  });

  factory WriteOff.fromJson(Map<String, Object?> json) =>
      _$WriteOffFromJson(json);

  final String uuid;
  final num? quantity;
  final String unit;
  final String reason;
  final String? note;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'shift_id')
  final int? shiftId;

  /// P6-19: the manager whose PIN approved it on the terminal (null: the caller's own permission)
  @JsonKey(name: 'approved_by')
  final dynamic approvedBy;

  Map<String, Object?> toJson() => _$WriteOffToJson(this);
}
