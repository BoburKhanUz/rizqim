// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'payments7.g.dart';

@JsonSerializable()
class Payments7 {
  const Payments7({
    required this.uuid,
    required this.kind,
    required this.kindLabel,
    required this.dueDate,
    required this.amount,
    required this.status,
    required this.statusLabel,
    required this.tender,
    required this.paidAt,
    required this.fiscalReceiptUuid,
  });

  factory Payments7.fromJson(Map<String, Object?> json) =>
      _$Payments7FromJson(json);

  final String uuid;
  final String kind;
  @JsonKey(name: 'kind_label')
  final String kindLabel;
  @JsonKey(name: 'due_date')
  final String? dueDate;
  final String amount;
  final String status;
  @JsonKey(name: 'status_label')
  final String statusLabel;
  final String? tender;
  @JsonKey(name: 'paid_at')
  final String? paidAt;
  @JsonKey(name: 'fiscal_receipt_uuid')
  final String? fiscalReceiptUuid;

  Map<String, Object?> toJson() => _$Payments7ToJson(this);
}
