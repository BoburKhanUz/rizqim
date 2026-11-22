// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data108.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data108 _$Data108FromJson(Map<String, dynamic> json) => Data108(
  branchId: (json['branch_id'] as num?)?.toInt(),
  ingredients: (json['ingredients'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data108ToJson(Data108 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'ingredients': instance.ingredients,
};
