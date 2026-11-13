// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data194.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data194 _$Data194FromJson(Map<String, dynamic> json) => Data194(
  requests: (json['requests'] as List<dynamic>)
      .map((e) => Requests.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data194ToJson(Data194 instance) => <String, dynamic>{
  'requests': instance.requests,
};
