// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'extra_bills.g.dart';

@JsonSerializable()
class ExtraBills {
  const ExtraBills({
    required this.uuid,
    required this.status,
    required this.total,
    required this.paidAmount,
  });

  factory ExtraBills.fromJson(Map<String, Object?> json) =>
      _$ExtraBillsFromJson(json);

  final String uuid;
  final String status;
  final String total;
  @JsonKey(name: 'paid_amount')
  final String paidAmount;

  Map<String, Object?> toJson() => _$ExtraBillsToJson(this);
}
