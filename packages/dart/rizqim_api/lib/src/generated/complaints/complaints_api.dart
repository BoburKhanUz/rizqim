// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_complaints_response.dart';
import '../models/post_v1_complaints_id_escalate_response.dart';
import '../models/post_v1_complaints_id_reply_response.dart';
import '../models/post_v1_complaints_response.dart';
import '../models/v1_complaints_id_reply_request_body.dart';
import '../models/v1_complaints_request_body.dart';

part 'complaints_api.g.dart';

@RestApi()
abstract class ComplaintsApi {
  factory ComplaintsApi(Dio dio, {String? baseUrl}) = _ComplaintsApi;

  /// Get list of complaints based on user role
  @GET('/v1/complaints')
  Future<GetV1ComplaintsResponse> complaintIndex();

  /// Customer files a complaint
  @POST('/v1/complaints')
  Future<PostV1ComplaintsResponse> complaintStore({
    @Body() required V1ComplaintsRequestBody body,
  });

  /// Customer escalates complaint to SuperAdmin
  @POST('/v1/complaints/{id}/escalate')
  Future<PostV1ComplaintsIdEscalateResponse> complaintEscalate({
    @Path('id') required String id,
  });

  /// Owner or Manager replies to the complaint
  @POST('/v1/complaints/{id}/reply')
  Future<PostV1ComplaintsIdReplyResponse> complaintReply({
    @Path('id') required String id,
    @Body() required V1ComplaintsIdReplyRequestBody body,
  });
}
