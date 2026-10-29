// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by4.dart';

part 'corrections5.g.dart';

@JsonSerializable()
class Corrections5 {
  const Corrections5({
    required this.amount,
    required this.mode,
    required this.staffId,
    required this.reason,
    required this.by,
    required this.approvedBy,
    required this.createdAt,
  });

  factory Corrections5.fromJson(Map<String, Object?> json) =>
      _$Corrections5FromJson(json);

  final String amount;
  final String mode;
  @JsonKey(name: 'staff_id')
  final int? staffId;
  final String? reason;
  final By4? by;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Corrections5ToJson(this);
}
