// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines15.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines15 _$Lines15FromJson(Map<String, dynamic> json) => Lines15(
  name: json['name'] as String,
  mxikCode: json['mxik_code'] as String,
  packageCode: json['package_code'] as String?,
  unit: json['unit'] as String,
  quantity: json['quantity'] as String,
  unitPrice: json['unit_price'] as String,
  amount: json['amount'] as String,
  vatRate: json['vat_rate'] as String,
  vatAmount: json['vat_amount'] as String,
  noVat: json['no_vat'] as bool,
  commissionerTin: json['commissioner_tin'] as String?,
  markingCode: json['marking_code'] as String?,
);

Map<String, dynamic> _$Lines15ToJson(Lines15 instance) => <String, dynamic>{
  'name': instance.name,
  'mxik_code': instance.mxikCode,
  'package_code': instance.packageCode,
  'unit': instance.unit,
  'quantity': instance.quantity,
  'unit_price': instance.unitPrice,
  'amount': instance.amount,
  'vat_rate': instance.vatRate,
  'vat_amount': instance.vatAmount,
  'no_vat': instance.noVat,
  'commissioner_tin': instance.commissionerTin,
  'marking_code': instance.markingCode,
};
