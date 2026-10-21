// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data26.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data26 _$Data26FromJson(Map<String, dynamic> json) => Data26(
  data: (json['data'] as List<dynamic>)
      .map((e) => Data25.fromJson(e as Map<String, dynamic>))
      .toList(),
  currentPage: (json['current_page'] as num).toInt(),
  lastPage: (json['last_page'] as num).toInt(),
  perPage: (json['per_page'] as num).toInt(),
  total: (json['total'] as num).toInt(),
);

Map<String, dynamic> _$Data26ToJson(Data26 instance) => <String, dynamic>{
  'data': instance.data,
  'current_page': instance.currentPage,
  'last_page': instance.lastPage,
  'per_page': instance.perPage,
  'total': instance.total,
};
