// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'entries.dart';

part 'data76.g.dart';

@JsonSerializable()
class Data76 {
  const Data76({required this.branchId, required this.entries});

  factory Data76.fromJson(Map<String, Object?> json) => _$Data76FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final List<Entries> entries;

  Map<String, Object?> toJson() => _$Data76ToJson(this);
}
