// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'entries.dart';

part 'data134.g.dart';

@JsonSerializable()
class Data134 {
  const Data134({required this.branchId, required this.entries});

  factory Data134.fromJson(Map<String, Object?> json) =>
      _$Data134FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final List<Entries> entries;

  Map<String, Object?> toJson() => _$Data134ToJson(this);
}
