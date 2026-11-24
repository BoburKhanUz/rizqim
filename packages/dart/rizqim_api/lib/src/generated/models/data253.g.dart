// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data253.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data253 _$Data253FromJson(Map<String, dynamic> json) => Data253(
  requests: (json['requests'] as List<dynamic>)
      .map((e) => Requests.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data253ToJson(Data253 instance) => <String, dynamic>{
  'requests': instance.requests,
};
