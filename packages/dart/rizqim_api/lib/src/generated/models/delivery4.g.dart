// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delivery4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Delivery4 _$Delivery4FromJson(Map<String, dynamic> json) => Delivery4(
  address: json['address'] as String?,
  latitude: json['latitude'] as num?,
  longitude: json['longitude'] as num?,
  zoneName: json['zone_name'] as String?,
);

Map<String, dynamic> _$Delivery4ToJson(Delivery4 instance) => <String, dynamic>{
  'address': instance.address,
  'latitude': instance.latitude,
  'longitude': instance.longitude,
  'zone_name': instance.zoneName,
};
