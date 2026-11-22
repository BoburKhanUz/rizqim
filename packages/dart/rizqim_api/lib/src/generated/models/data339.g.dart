// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data339.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data339 _$Data339FromJson(Map<String, dynamic> json) => Data339(
  businessDate: json['business_date'] as String,
  preparing: (json['preparing'] as List<dynamic>)
      .map((e) => Preparing2.fromJson(e as Map<String, dynamic>))
      .toList(),
  ready: (json['ready'] as List<dynamic>)
      .map((e) => Ready2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data339ToJson(Data339 instance) => <String, dynamic>{
  'business_date': instance.businessDate,
  'preparing': instance.preparing,
  'ready': instance.ready,
};
