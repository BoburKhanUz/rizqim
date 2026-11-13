// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers41.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers41 _$Modifiers41FromJson(Map<String, dynamic> json) => Modifiers41(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers41ToJson(Modifiers41 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
