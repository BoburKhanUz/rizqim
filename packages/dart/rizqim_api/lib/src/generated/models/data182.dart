// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'entries.dart';

part 'data182.g.dart';

@JsonSerializable()
class Data182 {
  const Data182({required this.branchId, required this.entries});

  factory Data182.fromJson(Map<String, Object?> json) =>
      _$Data182FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final List<Entries> entries;

  Map<String, Object?> toJson() => _$Data182ToJson(this);
}
