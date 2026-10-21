// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers8 _$Modifiers8FromJson(Map<String, dynamic> json) => Modifiers8(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers8ToJson(Modifiers8 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
