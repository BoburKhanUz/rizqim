// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'voucher.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Voucher _$VoucherFromJson(Map<String, dynamic> json) => Voucher(
  uuid: json['uuid'] as String,
  code: json['code'] as String,
  kind: json['kind'] as String,
  faceValue: json['face_value'] as String,
  balance: json['balance'] as String,
  currency: json['currency'] as String,
  validFrom: json['valid_from'] as String,
  validUntil: json['valid_until'] as String?,
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  branch: json['branch'] == null
      ? null
      : Branch10.fromJson(json['branch'] as Map<String, dynamic>),
  sale: Sale2.fromJson(json['sale'] as Map<String, dynamic>),
  buyer: Buyer2.fromJson(json['buyer'] as Map<String, dynamic>),
  note: json['note'] as String?,
  fiscal: Fiscal5.fromJson(json['fiscal'] as Map<String, dynamic>),
  blocked: json['blocked'] == null
      ? null
      : Blocked3.fromJson(json['blocked'] as Map<String, dynamic>),
  print: Print2.fromJson(json['print'] as Map<String, dynamic>),
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$VoucherToJson(Voucher instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'code': instance.code,
  'kind': instance.kind,
  'face_value': instance.faceValue,
  'balance': instance.balance,
  'currency': instance.currency,
  'valid_from': instance.validFrom,
  'valid_until': instance.validUntil,
  'status': instance.status,
  'status_label': instance.statusLabel,
  'branch': instance.branch,
  'sale': instance.sale,
  'buyer': instance.buyer,
  'note': instance.note,
  'fiscal': instance.fiscal,
  'blocked': instance.blocked,
  'print': instance.print,
  'created_at': instance.createdAt,
};
