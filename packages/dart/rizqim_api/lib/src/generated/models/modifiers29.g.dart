// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers29.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers29 _$Modifiers29FromJson(Map<String, dynamic> json) => Modifiers29(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers29ToJson(Modifiers29 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
