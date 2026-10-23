// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delivery9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Delivery9 _$Delivery9FromJson(Map<String, dynamic> json) => Delivery9(
  address: json['address'] as String?,
  latitude: json['latitude'] as num?,
  longitude: json['longitude'] as num?,
  zoneName: json['zone_name'] as String?,
);

Map<String, dynamic> _$Delivery9ToJson(Delivery9 instance) => <String, dynamic>{
  'address': instance.address,
  'latitude': instance.latitude,
  'longitude': instance.longitude,
  'zone_name': instance.zoneName,
};
