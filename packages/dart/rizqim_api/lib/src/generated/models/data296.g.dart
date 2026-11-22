// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data296.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data296 _$Data296FromJson(Map<String, dynamic> json) => Data296(
  businessDate: json['business_date'] as String,
  preparing: (json['preparing'] as List<dynamic>)
      .map((e) => Preparing.fromJson(e as Map<String, dynamic>))
      .toList(),
  ready: (json['ready'] as List<dynamic>)
      .map((e) => Ready.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data296ToJson(Data296 instance) => <String, dynamic>{
  'business_date': instance.businessDate,
  'preparing': instance.preparing,
  'ready': instance.ready,
};
