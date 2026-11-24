// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data135.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data135 _$Data135FromJson(Map<String, dynamic> json) => Data135(
  extras: (json['extras'] as List<dynamic>)
      .map((e) => Extras.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data135ToJson(Data135 instance) => <String, dynamic>{
  'extras': instance.extras,
};
