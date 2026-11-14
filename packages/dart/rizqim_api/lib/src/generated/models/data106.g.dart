// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data106.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data106 _$Data106FromJson(Map<String, dynamic> json) => Data106(
  branchId: (json['branch_id'] as num?)?.toInt(),
  ingredients: (json['ingredients'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data106ToJson(Data106 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'ingredients': instance.ingredients,
};
