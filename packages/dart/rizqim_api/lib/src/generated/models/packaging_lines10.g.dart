// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'packaging_lines10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PackagingLines10 _$PackagingLines10FromJson(Map<String, dynamic> json) =>
    PackagingLines10(
      ruleId: (json['rule_id'] as num).toInt(),
      name: json['name'] as String,
      quantity: (json['quantity'] as num).toInt(),
      unit: json['unit'] as String,
      amount: json['amount'] as String,
    );

Map<String, dynamic> _$PackagingLines10ToJson(PackagingLines10 instance) =>
    <String, dynamic>{
      'rule_id': instance.ruleId,
      'name': instance.name,
      'quantity': instance.quantity,
      'unit': instance.unit,
      'amount': instance.amount,
    };
