// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers23.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers23 _$Modifiers23FromJson(Map<String, dynamic> json) => Modifiers23(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers23ToJson(Modifiers23 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
