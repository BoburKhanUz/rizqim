// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'courier.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Courier _$CourierFromJson(Map<String, dynamic> json) => Courier(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String?,
  phone: json['phone'] as String?,
);

Map<String, dynamic> _$CourierToJson(Courier instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'phone': instance.phone,
};
