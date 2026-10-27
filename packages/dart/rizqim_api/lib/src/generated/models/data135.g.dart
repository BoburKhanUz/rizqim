// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data135.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data135 _$Data135FromJson(Map<String, dynamic> json) => Data135(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data135ToJson(Data135 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
