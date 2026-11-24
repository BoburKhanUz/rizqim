// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data139.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data139 _$Data139FromJson(Map<String, dynamic> json) => Data139(
  halls: (json['halls'] as List<dynamic>)
      .map((e) => Halls.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data139ToJson(Data139 instance) => <String, dynamic>{
  'halls': instance.halls,
};
