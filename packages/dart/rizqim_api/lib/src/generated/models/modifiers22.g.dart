// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers22.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers22 _$Modifiers22FromJson(Map<String, dynamic> json) => Modifiers22(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers22ToJson(Modifiers22 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
