// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data218.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data218 _$Data218FromJson(Map<String, dynamic> json) => Data218(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data218ToJson(Data218 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
