// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data241.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data241 _$Data241FromJson(Map<String, dynamic> json) => Data241(
  staff: (json['staff'] as List<dynamic>)
      .map((e) => Staff11.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data241ToJson(Data241 instance) => <String, dynamic>{
  'staff': instance.staff,
};
