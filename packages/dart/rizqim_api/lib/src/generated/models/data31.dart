// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modules.dart';

part 'data31.g.dart';

@JsonSerializable()
class Data31 {
  const Data31({
    required this.branchId,
    required this.modules,
    required this.plans,
  });

  factory Data31.fromJson(Map<String, Object?> json) => _$Data31FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final Modules modules;
  final List<List<dynamic>> plans;

  Map<String, Object?> toJson() => _$Data31ToJson(this);
}
