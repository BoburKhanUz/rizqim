// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'batches2.dart';

part 'data27.g.dart';

@JsonSerializable()
class Data27 {
  const Data27({required this.branchId, required this.batches});

  factory Data27.fromJson(Map<String, Object?> json) => _$Data27FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final List<Batches2> batches;

  Map<String, Object?> toJson() => _$Data27ToJson(this);
}
