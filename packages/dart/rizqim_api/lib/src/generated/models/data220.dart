// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'type6.dart';
import 'status5.dart';
import 'payment_form.dart';
import 'lines26.dart';
import 'payments27.dart';

part 'data220.g.dart';

@JsonSerializable()
class Data220 {
  const Data220({
    required this.uuid,
    required this.type,
    required this.status,
    required this.paymentForm,
    required this.nonFiscal,
    required this.total,
    required this.vatTotal,
    required this.fiscalSign,
    required this.receiptNumber,
    required this.qrUrl,
    required this.terminalId,
    required this.error,
    required this.issuedAt,
    required this.createdAt,
    required this.branchId,
    required this.originalReceiptUuid,
    required this.lines,
    required this.payments,
  });

  factory Data220.fromJson(Map<String, Object?> json) =>
      _$Data220FromJson(json);

  final String uuid;
  final Type6 type;
  final Status5 status;
  @JsonKey(name: 'payment_form')
  final PaymentForm paymentForm;
  @JsonKey(name: 'non_fiscal')
  final bool nonFiscal;
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
  @JsonKey(name: 'issued_at')
  final String? issuedAt;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'original_receipt_uuid')
  final String? originalReceiptUuid;
  final List<Lines26> lines;
  final List<Payments27> payments;

  Map<String, Object?> toJson() => _$Data220ToJson(this);
}
