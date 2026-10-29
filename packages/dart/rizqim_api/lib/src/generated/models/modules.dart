// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'modules.g.dart';

@JsonSerializable()
class Modules {
  const Modules({required this.batches, required this.batchWriteoff});

  factory Modules.fromJson(Map<String, Object?> json) =>
      _$ModulesFromJson(json);

  final bool batches;
  @JsonKey(name: 'batch_writeoff')
  final bool batchWriteoff;

  Map<String, Object?> toJson() => _$ModulesToJson(this);
}
