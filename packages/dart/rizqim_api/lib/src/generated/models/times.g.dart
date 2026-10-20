// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'times.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Times _$TimesFromJson(Map<String, dynamic> json) => Times(
  averagePrepSeconds: (json['average_prep_seconds'] as num?)?.toInt(),
  averageDeliverySeconds: (json['average_delivery_seconds'] as num?)?.toInt(),
);

Map<String, dynamic> _$TimesToJson(Times instance) => <String, dynamic>{
  'average_prep_seconds': instance.averagePrepSeconds,
  'average_delivery_seconds': instance.averageDeliverySeconds,
};
