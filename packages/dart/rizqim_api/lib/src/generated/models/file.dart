// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'file.g.dart';

@JsonSerializable()
class File {
  const File({
    required this.version,
    required this.device,
    required this.kid,
    required this.createdAt,
    required this.count,
    required this.firstSeq,
    required this.lastSeq,
  });

  factory File.fromJson(Map<String, Object?> json) => _$FileFromJson(json);

  final int version;
  final String device;
  final int kid;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  final int count;
  @JsonKey(name: 'first_seq')
  final int? firstSeq;
  @JsonKey(name: 'last_seq')
  final int? lastSeq;

  Map<String, Object?> toJson() => _$FileToJson(this);
}
