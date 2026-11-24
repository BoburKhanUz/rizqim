// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modules.dart';

part 'data32.g.dart';

@JsonSerializable()
class Data32 {
  const Data32({
    required this.branchId,
    required this.modules,
    required this.plans,
  });

  factory Data32.fromJson(Map<String, Object?> json) => _$Data32FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final Modules modules;
  final List<List<dynamic>> plans;

  Map<String, Object?> toJson() => _$Data32ToJson(this);
}
