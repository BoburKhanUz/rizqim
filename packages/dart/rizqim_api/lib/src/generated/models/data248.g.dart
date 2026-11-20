// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data248.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data248 _$Data248FromJson(Map<String, dynamic> json) => Data248(
  requests: (json['requests'] as List<dynamic>)
      .map((e) => Requests.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data248ToJson(Data248 instance) => <String, dynamic>{
  'requests': instance.requests,
};
