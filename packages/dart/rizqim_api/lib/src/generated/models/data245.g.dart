// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data245.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data245 _$Data245FromJson(Map<String, dynamic> json) => Data245(
  staff: (json['staff'] as List<dynamic>)
      .map((e) => Staff11.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data245ToJson(Data245 instance) => <String, dynamic>{
  'staff': instance.staff,
};
