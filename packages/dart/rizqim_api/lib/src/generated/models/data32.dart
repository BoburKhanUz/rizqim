// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'write_offs.dart';

part 'data32.g.dart';

@JsonSerializable()
class Data32 {
  const Data32({
    required this.branchId,
    required this.from,
    required this.to,
    required this.reasons,
    required this.writeOffs,
  });

  factory Data32.fromJson(Map<String, Object?> json) => _$Data32FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String from;
  final String to;
  final List<dynamic> reasons;
  @JsonKey(name: 'write_offs')
  final List<WriteOffs> writeOffs;

  Map<String, Object?> toJson() => _$Data32ToJson(this);
}
