// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'length_aware_paginator.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

LengthAwarePaginator _$LengthAwarePaginatorFromJson(
  Map<String, dynamic> json,
) => LengthAwarePaginator(
  currentPage: (json['current_page'] as num).toInt(),
  data: json['data'] as String,
  firstPageUrl: json['first_page_url'] as String,
  from: (json['from'] as num?)?.toInt(),
  lastPage: (json['last_page'] as num).toInt(),
  lastPageUrl: json['last_page_url'] as String,
  links: json['links'] as Map<String, dynamic>,
  nextPageUrl: json['next_page_url'] as String,
  path: json['path'] as String?,
  perPage: (json['per_page'] as num).toInt(),
  prevPageUrl: json['prev_page_url'] as String?,
  to: (json['to'] as num?)?.toInt(),
  total: (json['total'] as num).toInt(),
);

Map<String, dynamic> _$LengthAwarePaginatorToJson(
  LengthAwarePaginator instance,
) => <String, dynamic>{
  'current_page': instance.currentPage,
  'data': instance.data,
  'first_page_url': instance.firstPageUrl,
  'from': instance.from,
  'last_page': instance.lastPage,
  'last_page_url': instance.lastPageUrl,
  'links': instance.links,
  'next_page_url': instance.nextPageUrl,
  'path': instance.path,
  'per_page': instance.perPage,
  'prev_page_url': instance.prevPageUrl,
  'to': instance.to,
  'total': instance.total,
};
