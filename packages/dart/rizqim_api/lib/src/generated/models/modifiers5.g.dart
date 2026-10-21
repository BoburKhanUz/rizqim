// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers5 _$Modifiers5FromJson(Map<String, dynamic> json) => Modifiers5(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers5ToJson(Modifiers5 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
