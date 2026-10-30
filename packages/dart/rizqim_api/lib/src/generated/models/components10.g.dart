// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'components10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Components10 _$Components10FromJson(Map<String, dynamic> json) => Components10(
  type: json['type'] as String,
  label: json['label'] as String,
  ruleUuid: json['rule_uuid'] as String?,
  days: (json['days'] as num).toInt(),
  quantity: json['quantity'] as String?,
  rate: json['rate'] as String?,
  baseValue: json['base'] as String?,
  amount: json['amount'] as String,
  detail: json['detail'] as String,
);

Map<String, dynamic> _$Components10ToJson(Components10 instance) =>
    <String, dynamic>{
      'type': instance.type,
      'label': instance.label,
      'rule_uuid': instance.ruleUuid,
      'days': instance.days,
      'quantity': instance.quantity,
      'rate': instance.rate,
      'base': instance.baseValue,
      'amount': instance.amount,
      'detail': instance.detail,
    };
