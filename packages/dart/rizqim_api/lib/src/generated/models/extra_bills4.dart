// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'extra_bills4.g.dart';

@JsonSerializable()
class ExtraBills4 {
  const ExtraBills4({
    required this.uuid,
    required this.status,
    required this.total,
    required this.paidAmount,
  });

  factory ExtraBills4.fromJson(Map<String, Object?> json) =>
      _$ExtraBills4FromJson(json);

  final String uuid;
  final String status;
  final String total;
  @JsonKey(name: 'paid_amount')
  final String paidAmount;

  Map<String, Object?> toJson() => _$ExtraBills4ToJson(this);
}
