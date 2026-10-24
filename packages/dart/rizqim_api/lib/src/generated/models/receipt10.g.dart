// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'receipt10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Receipt10 _$Receipt10FromJson(Map<String, dynamic> json) => Receipt10(
  uuid: json['uuid'] as String,
  type: Type6.fromJson(json['type'] as String),
  status: Status5.fromJson(json['status'] as String),
  paymentForm: PaymentForm.fromJson(json['payment_form'] as String),
  nonFiscal: json['non_fiscal'] as bool,
  total: json['total'] as String,
  vatTotal: json['vat_total'] as String,
  fiscalSign: json['fiscal_sign'] as String?,
  receiptNumber: json['receipt_number'] as String?,
  qrUrl: json['qr_url'] as String?,
  terminalId: json['terminal_id'] as String?,
  error: json['error'] as String?,
  issuedAt: json['issued_at'] as String?,
  createdAt: json['created_at'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
  originalReceiptUuid: json['original_receipt_uuid'] as String?,
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines17.fromJson(e as Map<String, dynamic>))
      .toList(),
  payments: (json['payments'] as List<dynamic>)
      .map((e) => Payments31.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Receipt10ToJson(Receipt10 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'type': instance.type,
  'status': instance.status,
  'payment_form': instance.paymentForm,
  'non_fiscal': instance.nonFiscal,
  'total': instance.total,
  'vat_total': instance.vatTotal,
  'fiscal_sign': instance.fiscalSign,
  'receipt_number': instance.receiptNumber,
  'qr_url': instance.qrUrl,
  'terminal_id': instance.terminalId,
  'error': instance.error,
  'issued_at': instance.issuedAt,
  'created_at': instance.createdAt,
  'branch_id': instance.branchId,
  'original_receipt_uuid': instance.originalReceiptUuid,
  'lines': instance.lines,
  'payments': instance.payments,
};
