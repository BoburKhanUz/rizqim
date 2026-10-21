// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delivery2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Delivery2 _$Delivery2FromJson(Map<String, dynamic> json) => Delivery2(
  address: json['address'] as String?,
  latitude: json['latitude'] as num?,
  longitude: json['longitude'] as num?,
  zoneName: json['zone_name'] as String?,
);

Map<String, dynamic> _$Delivery2ToJson(Delivery2 instance) => <String, dynamic>{
  'address': instance.address,
  'latitude': instance.latitude,
  'longitude': instance.longitude,
  'zone_name': instance.zoneName,
};
