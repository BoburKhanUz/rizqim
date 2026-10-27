// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data80.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data80 _$Data80FromJson(Map<String, dynamic> json) => Data80(
  data: (json['data'] as List<dynamic>)
      .map((e) => Data79.fromJson(e as Map<String, dynamic>))
      .toList(),
  currentPage: (json['current_page'] as num).toInt(),
  lastPage: (json['last_page'] as num).toInt(),
  perPage: (json['per_page'] as num).toInt(),
  total: (json['total'] as num).toInt(),
  from: (json['from'] as num?)?.toInt(),
  to: (json['to'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data80ToJson(Data80 instance) => <String, dynamic>{
  'data': instance.data,
  'current_page': instance.currentPage,
  'last_page': instance.lastPage,
  'per_page': instance.perPage,
  'total': instance.total,
  'from': instance.from,
  'to': instance.to,
};
