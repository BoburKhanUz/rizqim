// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data251.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data251 _$Data251FromJson(Map<String, dynamic> json) => Data251(
  requests: (json['requests'] as List<dynamic>)
      .map((e) => Requests.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data251ToJson(Data251 instance) => <String, dynamic>{
  'requests': instance.requests,
};
