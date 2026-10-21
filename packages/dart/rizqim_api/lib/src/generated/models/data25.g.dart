// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data25.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data25 _$Data25FromJson(Map<String, dynamic> json) => Data25(
  branchId: (json['branch_id'] as num).toInt(),
  batches: (json['batches'] as List<dynamic>)
      .map((e) => Batches2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data25ToJson(Data25 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'batches': instance.batches,
};
