// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'type6.dart';
import 'status5.dart';
import 'payment_form.dart';
import 'lines3.dart';
import 'payments3.dart';

part 'data68.g.dart';

@JsonSerializable()
class Data68 {
  const Data68({
    required this.uuid,
    required this.type,
    required this.status,
    required this.paymentForm,
    required this.driver,
    required this.provider,
    required this.nonFiscal,
    required this.taxRegime,
    required this.total,
    required this.vatTotal,
    required this.fiscalSign,
    required this.receiptNumber,
    required this.qrUrl,
    required this.terminalId,
    required this.error,
    required this.attempts,
    required this.nextAttemptAt,
    required this.issuedAt,
    required this.createdAt,
    required this.branchId,
    required this.deviceId,
    required this.subjectType,
    required this.subjectId,
    required this.originalReceiptUuid,
    required this.lines,
    required this.payments,
  });

  factory Data68.fromJson(Map<String, Object?> json) => _$Data68FromJson(json);

  final String uuid;
  final Type6 type;
  final Status5 status;
  @JsonKey(name: 'payment_form')
  final PaymentForm paymentForm;
  final String driver;
  final String? provider;
  @JsonKey(name: 'non_fiscal')
  final bool nonFiscal;
  @JsonKey(name: 'tax_regime')
  final String? taxRegime;
  final String total;
  @JsonKey(name: 'vat_total')
  final String vatTotal;
  @JsonKey(name: 'fiscal_sign')
  final String? fiscalSign;
  @JsonKey(name: 'receipt_number')
  final String? receiptNumber;
  @JsonKey(name: 'qr_url')
  final String? qrUrl;
  @JsonKey(name: 'terminal_id')
  final String? terminalId;
  final String? error;
  final int attempts;
  @JsonKey(name: 'next_attempt_at')
  final String? nextAttemptAt;
  @JsonKey(name: 'issued_at')
  final String? issuedAt;
  @JsonKey(name: 'created_at')
  final String createdAt;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'device_id')
  final int? deviceId;
  @JsonKey(name: 'subject_type')
  final String? subjectType;
  @JsonKey(name: 'subject_id')
  final int? subjectId;
  @JsonKey(name: 'original_receipt_uuid')
  final String? originalReceiptUuid;
  final List<Lines3> lines;
  final List<Payments3> payments;

  Map<String, Object?> toJson() => _$Data68ToJson(this);
}
