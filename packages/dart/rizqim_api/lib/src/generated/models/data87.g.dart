// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data87.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data87 _$Data87FromJson(Map<String, dynamic> json) => Data87(
  uuid: json['uuid'] as String,
  type: Type6.fromJson(json['type'] as String),
  status: Status5.fromJson(json['status'] as String),
  paymentForm: PaymentForm.fromJson(json['payment_form'] as String),
  driver: json['driver'] as String,
  provider: json['provider'] as String?,
  nonFiscal: json['non_fiscal'] as bool,
  taxRegime: json['tax_regime'] as String?,
  total: json['total'] as String,
  vatTotal: json['vat_total'] as String,
  fiscalSign: json['fiscal_sign'] as String?,
  receiptNumber: json['receipt_number'] as String?,
  qrUrl: json['qr_url'] as String?,
  terminalId: json['terminal_id'] as String?,
  error: json['error'] as String?,
  attempts: (json['attempts'] as num).toInt(),
  nextAttemptAt: json['next_attempt_at'] as String?,
  issuedAt: json['issued_at'] as String?,
  createdAt: json['created_at'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
  deviceId: (json['device_id'] as num?)?.toInt(),
  subjectType: json['subject_type'] as String?,
  subjectId: (json['subject_id'] as num?)?.toInt(),
  originalReceiptUuid: json['original_receipt_uuid'] as String?,
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines2.fromJson(e as Map<String, dynamic>))
      .toList(),
  payments: (json['payments'] as List<dynamic>)
      .map((e) => Payments2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data87ToJson(Data87 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'type': instance.type,
  'status': instance.status,
  'payment_form': instance.paymentForm,
  'driver': instance.driver,
  'provider': instance.provider,
  'non_fiscal': instance.nonFiscal,
  'tax_regime': instance.taxRegime,
  'total': instance.total,
  'vat_total': instance.vatTotal,
  'fiscal_sign': instance.fiscalSign,
  'receipt_number': instance.receiptNumber,
  'qr_url': instance.qrUrl,
  'terminal_id': instance.terminalId,
  'error': instance.error,
  'attempts': instance.attempts,
  'next_attempt_at': instance.nextAttemptAt,
  'issued_at': instance.issuedAt,
  'created_at': instance.createdAt,
  'branch_id': instance.branchId,
  'device_id': instance.deviceId,
  'subject_type': instance.subjectType,
  'subject_id': instance.subjectId,
  'original_receipt_uuid': instance.originalReceiptUuid,
  'lines': instance.lines,
  'payments': instance.payments,
};
