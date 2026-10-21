// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data53.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data53 _$Data53FromJson(Map<String, dynamic> json) => Data53(
  currentPage: (json['current_page'] as num).toInt(),
  data: (json['data'] as List<dynamic>)
      .map((e) => Data52.fromJson(e as Map<String, dynamic>))
      .toList(),
  perPage: (json['per_page'] as num).toInt(),
  total: (json['total'] as num).toInt(),
  lastPage: (json['last_page'] as num).toInt(),
);

Map<String, dynamic> _$Data53ToJson(Data53 instance) => <String, dynamic>{
  'current_page': instance.currentPage,
  'data': instance.data,
  'per_page': instance.perPage,
  'total': instance.total,
  'last_page': instance.lastPage,
};
