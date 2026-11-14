// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'extra_bills2.g.dart';

@JsonSerializable()
class ExtraBills2 {
  const ExtraBills2({
    required this.uuid,
    required this.status,
    required this.total,
    required this.paidAmount,
  });

  factory ExtraBills2.fromJson(Map<String, Object?> json) =>
      _$ExtraBills2FromJson(json);

  final String uuid;
  final String status;
  final String total;
  @JsonKey(name: 'paid_amount')
  final String paidAmount;

  Map<String, Object?> toJson() => _$ExtraBills2ToJson(this);
}
