// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'packaging_lines3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PackagingLines3 _$PackagingLines3FromJson(Map<String, dynamic> json) =>
    PackagingLines3(
      ruleId: (json['rule_id'] as num).toInt(),
      name: json['name'] as String,
      quantity: (json['quantity'] as num).toInt(),
      unit: json['unit'] as String,
      amount: json['amount'] as String,
    );

Map<String, dynamic> _$PackagingLines3ToJson(PackagingLines3 instance) =>
    <String, dynamic>{
      'rule_id': instance.ruleId,
      'name': instance.name,
      'quantity': instance.quantity,
      'unit': instance.unit,
      'amount': instance.amount,
    };
