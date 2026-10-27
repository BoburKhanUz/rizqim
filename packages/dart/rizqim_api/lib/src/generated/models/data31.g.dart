// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data31.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data31 _$Data31FromJson(Map<String, dynamic> json) => Data31(
  branchId: (json['branch_id'] as num).toInt(),
  batches: (json['batches'] as List<dynamic>)
      .map((e) => Batches2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data31ToJson(Data31 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'batches': instance.batches,
};
