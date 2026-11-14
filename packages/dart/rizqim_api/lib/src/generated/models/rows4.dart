// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rows4.g.dart';

@JsonSerializable()
class Rows4 {
  const Rows4({
    required this.paymentUuid,
    required this.kind,
    required this.tender,
    required this.amount,
    required this.retain,
    required this.refund,
    required this.refundPath,
  });

  factory Rows4.fromJson(Map<String, Object?> json) => _$Rows4FromJson(json);

  @JsonKey(name: 'payment_uuid')
  final String paymentUuid;
  final String kind;
  final String? tender;
  final String amount;
  final String retain;
  final String refund;
  @JsonKey(name: 'refund_path')
  final String? refundPath;

  Map<String, Object?> toJson() => _$Rows4ToJson(this);
}
