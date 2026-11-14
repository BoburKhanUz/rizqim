// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data246.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data246 _$Data246FromJson(Map<String, dynamic> json) => Data246(
  requests: (json['requests'] as List<dynamic>)
      .map((e) => Requests.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data246ToJson(Data246 instance) => <String, dynamic>{
  'requests': instance.requests,
};
