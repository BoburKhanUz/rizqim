// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'packaging_lines10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PackagingLines10 _$PackagingLines10FromJson(Map<String, dynamic> json) =>
    PackagingLines10(
      ruleId: (json['rule_id'] as num).toInt(),
      name: json['name'] as String,
      basis: json['basis'] as String,
      quantity: (json['quantity'] as num).toInt(),
      unit: json['unit'] as String,
      unitPrice: json['unit_price'] as String,
      amount: json['amount'] as String,
      mxik: json['mxik'] as String?,
      packageCode: json['package_code'] as String?,
      removed: json['removed'] as bool,
      removedReason: json['removed_reason'] as String?,
    );

Map<String, dynamic> _$PackagingLines10ToJson(PackagingLines10 instance) =>
    <String, dynamic>{
      'rule_id': instance.ruleId,
      'name': instance.name,
      'basis': instance.basis,
      'quantity': instance.quantity,
      'unit': instance.unit,
      'unit_price': instance.unitPrice,
      'amount': instance.amount,
      'mxik': instance.mxik,
      'package_code': instance.packageCode,
      'removed': instance.removed,
      'removed_reason': instance.removedReason,
    };
