// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data187.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data187 _$Data187FromJson(Map<String, dynamic> json) => Data187(
  requests: (json['requests'] as List<dynamic>)
      .map((e) => Requests.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data187ToJson(Data187 instance) => <String, dynamic>{
  'requests': instance.requests,
};
