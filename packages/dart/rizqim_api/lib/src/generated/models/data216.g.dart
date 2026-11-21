// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data216.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data216 _$Data216FromJson(Map<String, dynamic> json) => Data216(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data216ToJson(Data216 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
